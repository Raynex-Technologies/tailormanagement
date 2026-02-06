<?php

namespace App\Services\Orders;

use App\Enums\StockRequestStatus;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderStockRequest;
use App\Models\OrderStockRequestItem;
use App\Models\User;
use App\Notifications\StockRequestCreated;
use App\Notifications\StockRequestFulfilled;
use App\Notifications\StockRequestReviewed;
use App\Services\Inventory\StockMovementService;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockRequestService
{
    public function __construct(
        protected StockMovementService $stockMovementService
    ) {}

    /**
     * Create a new stock request for an order.
     *
     * @param  Order  $order  The order to request stock for
     * @param  array  $items  Array of [{inventory_item_id, qty_requested, note?}]
     * @param  string|null  $note  Overall request note
     * @param  User  $actor  The user creating the request
     */
    public function createRequest(Order $order, array $items, ?string $note, User $actor): OrderStockRequest
    {
        // Validate items
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'At least one item is required.',
            ]);
        }

        // Validate each item
        $branchId = $order->branch_id;
        foreach ($items as $index => $item) {
            if (empty($item['inventory_item_id'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.inventory_item_id" => 'Inventory item is required.',
                ]);
            }

            if (empty($item['qty_requested']) || $item['qty_requested'] <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.qty_requested" => 'Quantity must be greater than 0.',
                ]);
            }

            // Check item belongs to same branch
            $inventoryItem = InventoryItem::find($item['inventory_item_id']);
            if (! $inventoryItem || $inventoryItem->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    "items.{$index}.inventory_item_id" => 'Invalid inventory item or item belongs to different branch.',
                ]);
            }
        }

        return DB::transaction(function () use ($order, $items, $note, $actor, $branchId) {
            // Create the request
            $stockRequest = OrderStockRequest::create([
                'branch_id' => $branchId,
                'order_id' => $order->id,
                'requested_by' => $actor->id,
                'status' => StockRequestStatus::Requested,
                'note' => $note,
            ]);

            // Create request items
            foreach ($items as $item) {
                OrderStockRequestItem::create([
                    'order_stock_request_id' => $stockRequest->id,
                    'inventory_item_id' => $item['inventory_item_id'],
                    'qty_requested' => $item['qty_requested'],
                    'note' => $item['note'] ?? null,
                ]);
            }

            // Notify storekeepers in the same branch
            $this->notifyStorekeepers($stockRequest);

            return $stockRequest->load('items.inventoryItem', 'requester', 'order');
        });
    }

    /**
     * Review a stock request (approve or decline).
     *
     * @param  OrderStockRequest  $request  The request to review
     * @param  string  $decision  'approve' or 'decline'
     * @param  array  $approvedItems  Array of [{order_stock_request_item_id, qty_approved}] (for approve)
     * @param  string|null  $note  Handler note
     * @param  User  $actor  The user reviewing
     */
    public function reviewRequest(
        OrderStockRequest $request,
        string $decision,
        array $approvedItems,
        ?string $note,
        User $actor
    ): OrderStockRequest {
        if (! $request->canBeReviewed()) {
            throw ValidationException::withMessages([
                'status' => 'This request cannot be reviewed in its current state.',
            ]);
        }

        if (! in_array($decision, ['approve', 'decline'])) {
            throw ValidationException::withMessages([
                'decision' => 'Invalid decision. Must be "approve" or "decline".',
            ]);
        }

        return DB::transaction(function () use ($request, $decision, $approvedItems, $note, $actor) {
            if ($decision === 'decline') {
                $request->update([
                    'status' => StockRequestStatus::Declined,
                    'handled_by' => $actor->id,
                    'handler_note' => $note,
                ]);
            } else {
                // Approve - validate and set approved quantities
                $request->load('items');

                foreach ($request->items as $item) {
                    $approved = collect($approvedItems)->firstWhere('id', $item->id);
                    $qtyApproved = $approved['qty_approved'] ?? 0;

                    if ($qtyApproved < 0) {
                        throw ValidationException::withMessages([
                            "items.{$item->id}" => 'Approved quantity cannot be negative.',
                        ]);
                    }

                    if ($qtyApproved > $item->qty_requested) {
                        throw ValidationException::withMessages([
                            "items.{$item->id}" => 'Approved quantity cannot exceed requested quantity.',
                        ]);
                    }

                    $item->update(['qty_approved' => $qtyApproved]);
                }

                $request->update([
                    'status' => StockRequestStatus::Approved,
                    'handled_by' => $actor->id,
                    'handler_note' => $note,
                ]);
            }

            // Notify the requester
            $this->notifyRequester($request->fresh(['items', 'requester', 'handler', 'order']));

            return $request->fresh(['items.inventoryItem', 'requester', 'handler', 'order']);
        });
    }

    /**
     * Fulfill a stock request by issuing inventory items.
     *
     * @param  OrderStockRequest  $request  The approved request to fulfill
     * @param  array  $issueItems  Array of [{order_stock_request_item_id, qty_to_issue}]
     * @param  string|null  $note  Fulfillment note
     * @param  User  $actor  The user fulfilling
     */
    public function fulfillRequest(
        OrderStockRequest $request,
        array $issueItems,
        ?string $note,
        User $actor
    ): OrderStockRequest {
        if (! $request->canBeFulfilled()) {
            throw ValidationException::withMessages([
                'status' => 'This request cannot be fulfilled in its current state.',
            ]);
        }

        return DB::transaction(function () use ($request, $issueItems, $note, $actor) {
            // Lock the request for update
            $request = OrderStockRequest::lockForUpdate()->find($request->id);
            $request->load('items.inventoryItem.stock');

            foreach ($issueItems as $issueItem) {
                if (empty($issueItem['id']) || empty($issueItem['qty_to_issue'])) {
                    continue;
                }

                $requestItem = $request->items->firstWhere('id', $issueItem['id']);
                if (! $requestItem) {
                    continue;
                }

                $qtyToIssue = (float) $issueItem['qty_to_issue'];

                if ($qtyToIssue <= 0) {
                    continue;
                }

                // Check against remaining approved quantity
                $remaining = $requestItem->remaining_to_issue;
                if ($qtyToIssue > $remaining) {
                    throw ValidationException::withMessages([
                        "items.{$requestItem->id}" => "Cannot issue more than remaining approved quantity ({$remaining}).",
                    ]);
                }

                // Check stock availability
                $inventoryItem = $requestItem->inventoryItem;
                $currentStock = $inventoryItem->stock?->qty_on_hand ?? 0;

                if ($qtyToIssue > $currentStock) {
                    throw ValidationException::withMessages([
                        "items.{$requestItem->id}" => "Insufficient stock. Available: {$currentStock}, Requested: {$qtyToIssue}.",
                    ]);
                }

                // Issue the stock using StockMovementService
                $this->stockMovementService->issue(
                    $inventoryItem,
                    $qtyToIssue,
                    "Stock request fulfillment for Order #{$request->order->order_no}",
                    $actor,
                    $request
                );

                // Update qty_issued
                $requestItem->increment('qty_issued', $qtyToIssue);
            }

            // Reload and check if fully issued
            $request = $request->fresh(['items']);

            if ($request->isFullyIssued()) {
                $request->update([
                    'status' => StockRequestStatus::Fulfilled,
                    'handler_note' => $note ?: $request->handler_note,
                ]);

                // Notify the requester about fulfillment
                $this->notifyFulfillment($request->fresh(['items', 'requester', 'handler', 'order']));
            }

            return $request->fresh(['items.inventoryItem.stock', 'requester', 'handler', 'order']);
        });
    }

    /**
     * Notify storekeepers about a new stock request.
     */
    protected function notifyStorekeepers(OrderStockRequest $request): void
    {
        $storekeepers = User::whereHas('roles', fn ($q) => $q->where('name', 'storekeeper'))
            ->where('branch_id', $request->branch_id)
            ->get();

        // If no storekeepers, notify branch managers and admins
        if ($storekeepers->isEmpty()) {
            $storekeepers = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['branch_manager', 'admin']))
                ->where(function ($q) use ($request) {
                    $q->where('branch_id', $request->branch_id)
                        ->orWhereNull('branch_id');
                })
                ->get();
        }

        foreach ($storekeepers as $storekeeper) {
            $storekeeper->notify(new StockRequestCreated($request));
        }
    }

    /**
     * Notify the requester about review decision.
     */
    protected function notifyRequester(OrderStockRequest $request): void
    {
        if ($request->requester) {
            $request->requester->notify(new StockRequestReviewed($request));
        }

        // Also notify order watchers with notify_on_status_change
        $this->notifyOrderWatchers($request, 'reviewed');
    }

    /**
     * Notify the requester about fulfillment.
     */
    protected function notifyFulfillment(OrderStockRequest $request): void
    {
        if ($request->requester) {
            $request->requester->notify(new StockRequestFulfilled($request));
        }

        // Also notify order watchers
        $this->notifyOrderWatchers($request, 'fulfilled');
    }

    /**
     * Notify order watchers about stock request changes.
     */
    protected function notifyOrderWatchers(OrderStockRequest $request, string $type): void
    {
        $watchers = $request->order->watchers()
            ->where('notify_on_status_change', true)
            ->with('user')
            ->get();

        foreach ($watchers as $watcher) {
            if ($watcher->user && $watcher->user->id !== $request->requester?->id) {
                if ($type === 'reviewed') {
                    $watcher->user->notify(new StockRequestReviewed($request));
                } elseif ($type === 'fulfilled') {
                    $watcher->user->notify(new StockRequestFulfilled($request));
                }
            }
        }
    }
}

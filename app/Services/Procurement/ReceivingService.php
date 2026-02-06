<?php

namespace App\Services\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Notifications\GoodsReceivedNotification;
use App\Services\Inventory\StockMovementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ReceivingService
{
    public function __construct(
        protected StockMovementService $stockService
    ) {}

    /**
     * Receive goods for a purchase order.
     *
     * @param  PurchaseOrder  $po  The purchase order
     * @param  array  $receivedItems  Array of items to receive [{purchase_order_item_id, qty_received, unit_cost}]
     * @param  User  $actor  User performing the operation
     * @param  string|null  $note  Optional note
     */
    public function receive(PurchaseOrder $po, array $receivedItems, User $actor, ?string $note = null): GoodsReceipt
    {
        // Validate PO status
        if (! in_array($po->status, [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived])) {
            throw ValidationException::withMessages([
                'status' => 'Can only receive goods for sent or partially received POs.',
            ]);
        }

        if (empty($receivedItems)) {
            throw ValidationException::withMessages([
                'items' => 'At least one item must be received.',
            ]);
        }

        return DB::transaction(function () use ($po, $receivedItems, $actor, $note) {
            // Create GRN
            $grn = GoodsReceipt::create([
                'branch_id' => $po->branch_id,
                'purchase_order_id' => $po->id,
                'received_at' => now(),
                'received_by' => $actor->id,
                'note' => $note,
            ]);

            $hasReceivedSomething = false;

            foreach ($receivedItems as $receivedItem) {
                $qtyReceived = (float) ($receivedItem['qty_received'] ?? 0);
                if ($qtyReceived <= 0) {
                    continue;
                }

                // Find PO item
                $poItem = PurchaseOrderItem::find($receivedItem['purchase_order_item_id']);
                if (! $poItem || $poItem->purchase_order_id !== $po->id) {
                    continue;
                }

                // Validate qty doesn't exceed pending
                $pendingQty = $poItem->qty_ordered - $poItem->qty_received;
                if ($qtyReceived > $pendingQty) {
                    throw ValidationException::withMessages([
                        'qty' => "Cannot receive {$qtyReceived} for item '{$poItem->item_name}'. Only {$pendingQty} pending.",
                    ]);
                }

                // Unit cost (use from input or from PO item)
                $unitCost = (float) ($receivedItem['unit_cost'] ?? $poItem->unit_cost ?? 0);

                // Create GRN item
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $grn->id,
                    'inventory_item_id' => $poItem->inventory_item_id,
                    'qty_received' => $qtyReceived,
                    'unit_cost' => $unitCost,
                    'line_total' => $qtyReceived * $unitCost,
                ]);

                // Update PO item qty_received
                $poItem->increment('qty_received', $qtyReceived);

                // Update inventory if linked to an inventory item
                if ($poItem->inventory_item_id) {
                    $inventoryItem = InventoryItem::find($poItem->inventory_item_id);
                    if ($inventoryItem) {
                        $this->stockService->receive(
                            $inventoryItem,
                            $qtyReceived,
                            $unitCost,
                            "Received via GRN: {$grn->grn_no}",
                            $actor,
                            $grn
                        );
                    }
                }

                $hasReceivedSomething = true;
            }

            if (! $hasReceivedSomething) {
                throw ValidationException::withMessages([
                    'items' => 'No items were received. Please enter quantities to receive.',
                ]);
            }

            // Update PO status based on received quantities
            $this->updatePurchaseOrderStatus($po);

            // Send notifications
            $this->notifyOfGoodsReceived($grn, $po);

            return $grn->load(['items.inventoryItem', 'purchaseOrder', 'receiver']);
        });
    }

    /**
     * Update PO status based on received quantities.
     */
    protected function updatePurchaseOrderStatus(PurchaseOrder $po): void
    {
        $po->refresh();

        $totalOrdered = $po->items->sum('qty_ordered');
        $totalReceived = $po->items->sum('qty_received');

        if ($totalReceived >= $totalOrdered) {
            $po->update(['status' => PurchaseOrderStatus::Received]);
        } else {
            $po->update(['status' => PurchaseOrderStatus::PartiallyReceived]);
        }
    }

    /**
     * Notify relevant users about goods received.
     */
    protected function notifyOfGoodsReceived(GoodsReceipt $grn, PurchaseOrder $po): void
    {
        $recipients = collect();

        // Notify PO creator
        if ($po->creator) {
            $recipients->push($po->creator);
        }

        // Notify accountants in branch
        $accountants = \App\Models\User::role('accountant')
            ->where('branch_id', $po->branch_id)
            ->get();
        $recipients = $recipients->merge($accountants);

        // Notify storekeepers in branch
        $storekeepers = \App\Models\User::role('storekeeper')
            ->where('branch_id', $po->branch_id)
            ->get();
        $recipients = $recipients->merge($storekeepers);

        // Remove duplicates and the actor
        $recipients = $recipients->unique('id')
            ->filter(fn ($user) => $user->id !== $grn->received_by);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new GoodsReceivedNotification($grn));
        }
    }

    /**
     * Get POs eligible for receiving.
     */
    public function getReceivablePurchaseOrders(?int $branchId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = PurchaseOrder::whereIn('status', [
            PurchaseOrderStatus::Sent,
            PurchaseOrderStatus::PartiallyReceived,
        ])->with(['supplier', 'items']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->latest()->get();
    }
}

<?php

namespace App\Services\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Notifications\PurchaseRequestReviewed;
use App\Notifications\PurchaseRequestSubmitted;
use App\Services\Capital\CapitalAllocationService;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function __construct(
        protected CapitalAllocationService $capitalService
    ) {}

    /**
     * Create a draft purchase request.
     *
     * @param  User  $actor  The user creating the request
     * @param  array  $data  Request data including optional 'branch_id' for global admins
     */
    public function createDraft(User $actor, array $data): PurchaseRequest
    {
        if (empty($data['items']) || ! is_array($data['items'])) {
            throw ValidationException::withMessages([
                'items' => 'At least one item is required.',
            ]);
        }

        $explicitBranchId = $data['branch_id'] ?? null;
        $branchId = BranchContext::getEffectiveBranchId($explicitBranchId);
        $validatedItems = $this->validateDraftItems($data['items'], $branchId);

        return DB::transaction(function () use ($actor, $data, $branchId, $validatedItems) {
            // Create the PR
            $pr = PurchaseRequest::create([
                'branch_id' => $branchId,
                'requested_by' => $actor->id,
                'status' => PurchaseRequestStatus::Draft,
                'estimated_total' => 0,
                'note' => $data['note'] ?? null,
            ]);

            // Add items
            $estimatedTotal = 0;
            foreach ($validatedItems as $item) {
                $qty = $item['qty'];
                $unitPriceEst = $item['unit_price_est'];
                $lineTotalEst = $qty * $unitPriceEst;

                PurchaseRequestItem::create([
                    'purchase_request_id' => $pr->id,
                    'inventory_item_id' => $item['inventory_item_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'qty' => $qty,
                    'unit_price_est' => $unitPriceEst,
                    'line_total_est' => $lineTotalEst,
                ]);

                $estimatedTotal += $lineTotalEst;
            }

            // Update estimated total
            $pr->update(['estimated_total' => $estimatedTotal]);

            return $pr->load('items', 'requester');
        });
    }

    /**
     * Update a draft purchase request.
     */
    public function updateDraft(PurchaseRequest $pr, User $actor, array $data): PurchaseRequest
    {
        if ($pr->status !== PurchaseRequestStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft requests can be updated.',
            ]);
        }

        $validatedItems = isset($data['items']) && is_array($data['items'])
            ? $this->validateDraftItems($data['items'], $pr->branch_id)
            : null;

        return DB::transaction(function () use ($pr, $data, $validatedItems) {
            // Update note if provided
            if (isset($data['note'])) {
                $pr->update(['note' => $data['note']]);
            }

            // Update items if provided
            if ($validatedItems !== null) {
                // Delete existing items
                $pr->items()->delete();

                // Add new items
                $estimatedTotal = 0;
                foreach ($validatedItems as $item) {
                    $qty = $item['qty'];
                    $unitPriceEst = $item['unit_price_est'];
                    $lineTotalEst = $qty * $unitPriceEst;

                    PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        'inventory_item_id' => $item['inventory_item_id'] ?? null,
                        'item_name' => $item['item_name'],
                        'qty' => $qty,
                        'unit_price_est' => $unitPriceEst,
                        'line_total_est' => $lineTotalEst,
                    ]);

                    $estimatedTotal += $lineTotalEst;
                }

                $pr->update(['estimated_total' => $estimatedTotal]);
            }

            return $pr->fresh(['items', 'requester']);
        });
    }

    /**
     * Submit a draft purchase request.
     */
    public function submit(PurchaseRequest $pr, User $actor): PurchaseRequest
    {
        if ($pr->status !== PurchaseRequestStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft requests can be submitted.',
            ]);
        }

        if ($pr->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'Cannot submit a request with no items.',
            ]);
        }

        if (! $pr->items()->where('qty', '>', 0)->exists()) {
            throw ValidationException::withMessages([
                'items' => 'Cannot submit a request without valid item quantities.',
            ]);
        }

        return DB::transaction(function () use ($pr) {
            $pr->update(['status' => PurchaseRequestStatus::Submitted]);

            // Notify accountants in the branch
            $this->notifyAccountantsOfSubmission($pr);

            return $pr->fresh(['items', 'requester']);
        });
    }

    /**
     * Approve a purchase request.
     */
    public function approve(PurchaseRequest $pr, User $actor, array $reviewedItems, ?string $note = null): PurchaseRequest
    {
        if ($pr->status !== PurchaseRequestStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted requests can be approved.',
            ]);
        }

        $validatedReviewedItems = $this->validateReviewedItems($pr, $reviewedItems);

        // Find active allocation for this accountant
        $allocation = $this->capitalService->findActiveAllocationForAccountant(
            $actor->id,
            $pr->branch_id
        );

        if (! $allocation) {
            throw ValidationException::withMessages([
                'allocation' => 'No active capital allocation found for your account. Please contact an administrator.',
            ]);
        }

        return DB::transaction(function () use ($pr, $actor, $validatedReviewedItems, $note, $allocation) {
            // Update items with reviewed quantities/prices
            $approvedTotal = 0;
            foreach ($pr->items as $item) {
                $reviewedItem = $validatedReviewedItems[$item->id] ?? null;
                if ($reviewedItem) {
                    $qty = $reviewedItem['qty'];
                    $unitPrice = $reviewedItem['unit_price_est'];
                    $lineTotal = $qty * $unitPrice;

                    $item->update([
                        'qty' => $qty,
                        'unit_price_est' => $unitPrice,
                        'line_total_est' => $lineTotal,
                    ]);

                    $approvedTotal += $lineTotal;
                } else {
                    $approvedTotal += (float) $item->line_total_est;
                }
            }

            // Check available balance
            $availableBalance = $this->capitalService->availableBalance($allocation);
            if ($approvedTotal > $availableBalance) {
                throw ValidationException::withMessages([
                    'amount' => "Insufficient capital balance. Available: " . money_tzs($availableBalance) . ", Required: " . money_tzs($approvedTotal),
                ]);
            }

            // Create debit transaction
            $this->capitalService->createDebit(
                $allocation,
                $approvedTotal,
                $actor,
                $pr,
                "Approved PR: {$pr->request_no}"
            );

            // Update PR
            $pr->update([
                'status' => PurchaseRequestStatus::Approved,
                'reviewed_by' => $actor->id,
                'estimated_total' => $approvedTotal,
                'capital_allocation_id' => $allocation->id,
            ]);

            // Notify requester
            $this->notifyRequesterOfReview($pr, 'approved');

            return $pr->fresh(['items', 'requester', 'reviewer', 'capitalAllocation']);
        });
    }

    /**
     * Decline a purchase request.
     */
    public function decline(PurchaseRequest $pr, User $actor, ?string $note = null): PurchaseRequest
    {
        if ($pr->status !== PurchaseRequestStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted requests can be declined.',
            ]);
        }

        return DB::transaction(function () use ($pr, $actor, $note) {
            $pr->update([
                'status' => PurchaseRequestStatus::Declined,
                'reviewed_by' => $actor->id,
                'note' => $note ? ($pr->note ? "{$pr->note}\n\nDecline reason: {$note}" : "Decline reason: {$note}") : $pr->note,
            ]);

            // Notify requester
            $this->notifyRequesterOfReview($pr, 'declined');

            return $pr->fresh(['items', 'requester', 'reviewer']);
        });
    }

    /**
     * Convert approved PR to a Purchase Order.
     */
    public function convertToPo(PurchaseRequest $pr, User $actor, array $poData): PurchaseOrder
    {
        if ($pr->status !== PurchaseRequestStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'Only approved requests can be converted to PO.',
            ]);
        }

        if (empty($poData['supplier_id'])) {
            throw ValidationException::withMessages([
                'supplier_id' => 'A supplier must be selected.',
            ]);
        }

        return DB::transaction(function () use ($pr, $actor, $poData) {
            // Create PO - auto-set to 'Sent' so storekeeper can receive immediately
            // The accountant approved the PR and converted to PO, so it's ready for fulfillment
            $po = PurchaseOrder::create([
                'branch_id' => $pr->branch_id,
                'supplier_id' => $poData['supplier_id'],
                'purchase_request_id' => $pr->id,
                'status' => PurchaseOrderStatus::Sent, // Ready for receiving
                'subtotal' => $pr->estimated_total,
                'total' => $pr->estimated_total,
                'expected_date' => $poData['expected_date'] ?? null,
                'note' => $poData['note'] ?? null,
                'created_by' => $actor->id,
            ]);

            // Copy items from PR to PO
            foreach ($pr->items as $prItem) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'inventory_item_id' => $prItem->inventory_item_id,
                    'item_name' => $prItem->item_name,
                    'qty_ordered' => $prItem->qty,
                    'qty_received' => 0,
                    'unit_cost' => $prItem->unit_price_est,
                    'line_total' => $prItem->line_total_est,
                ]);
            }

            // Update PR status
            $pr->update(['status' => PurchaseRequestStatus::ConvertedToPo]);

            return $po->load(['items', 'supplier', 'purchaseRequest', 'creator']);
        });
    }

    /**
     * Notify accountants in the branch about a submitted PR.
     */
    protected function notifyAccountantsOfSubmission(PurchaseRequest $pr): void
    {
        $accountants = User::role('accountant')
            ->where('branch_id', $pr->branch_id)
            ->get();

        if ($accountants->isNotEmpty()) {
            Notification::send($accountants, new PurchaseRequestSubmitted($pr));
        }
    }

    /**
     * Notify the requester about review result.
     */
    protected function notifyRequesterOfReview(PurchaseRequest $pr, string $decision): void
    {
        if ($pr->requester) {
            $pr->requester->notify(new PurchaseRequestReviewed($pr, $decision));
        }
    }

    /**
     * Validate and normalize draft items before persisting them.
     *
     * @return array<int, array<string, int|float|string|null>>
     */
    protected function validateDraftItems(array $items, int $branchId): array
    {
        $messages = [];
        $validatedItems = [];

        foreach ($items as $index => $item) {
            $inventoryItemId = isset($item['inventory_item_id']) ? (int) $item['inventory_item_id'] : null;
            $itemName = trim((string) ($item['item_name'] ?? ''));
            $qty = (float) ($item['qty'] ?? 0);
            $unitPriceEst = (float) ($item['unit_price_est'] ?? 0);

            if ($qty <= 0) {
                $messages["items.{$index}.qty"] = 'Quantity must be greater than zero.';
            }

            if ($unitPriceEst < 0) {
                $messages["items.{$index}.unit_price_est"] = 'Estimated unit price cannot be negative.';
            }

            if ($inventoryItemId !== null) {
                $inventoryItem = InventoryItem::withoutGlobalScope(BranchScope::class)
                    ->where('branch_id', $branchId)
                    ->find($inventoryItemId);

                if (! $inventoryItem) {
                    $messages["items.{$index}.inventory_item_id"] = 'Selected inventory item is invalid for this branch.';
                    continue;
                }

                if ($itemName === '') {
                    $itemName = $inventoryItem->name;
                }
            }

            if ($itemName === '') {
                $messages["items.{$index}.item_name"] = 'Item name is required.';
            }

            if (isset($messages["items.{$index}.qty"]) || isset($messages["items.{$index}.unit_price_est"]) || isset($messages["items.{$index}.item_name"])) {
                continue;
            }

            $validatedItems[] = [
                'inventory_item_id' => $inventoryItemId,
                'item_name' => $itemName,
                'qty' => $qty,
                'unit_price_est' => $unitPriceEst,
            ];
        }

        if (! empty($messages)) {
            throw ValidationException::withMessages($messages);
        }

        if (empty($validatedItems)) {
            throw ValidationException::withMessages([
                'items' => 'At least one valid item is required.',
            ]);
        }

        return $validatedItems;
    }

    /**
     * Validate reviewed quantities and prices before approval.
     *
     * @return array<int, array{id:int, qty:float, unit_price_est:float}>
     */
    protected function validateReviewedItems(PurchaseRequest $pr, array $reviewedItems): array
    {
        $messages = [];
        $validatedItems = [];
        $requestItemIds = $pr->items()->pluck('id')->all();

        foreach ($reviewedItems as $index => $item) {
            $itemId = isset($item['id']) ? (int) $item['id'] : null;

            if (! $itemId || ! in_array($itemId, $requestItemIds, true)) {
                $messages["reviewedItems.{$index}.id"] = 'Reviewed item is invalid.';
                continue;
            }

            $qty = (float) ($item['qty'] ?? 0);
            $unitPriceEst = (float) ($item['unit_price_est'] ?? 0);

            if ($qty <= 0) {
                $messages["reviewedItems.{$itemId}.qty"] = 'Approved quantity must be greater than zero.';
            }

            if ($unitPriceEst < 0) {
                $messages["reviewedItems.{$itemId}.unit_price_est"] = 'Approved unit price cannot be negative.';
            }

            if (isset($messages["reviewedItems.{$itemId}.qty"]) || isset($messages["reviewedItems.{$itemId}.unit_price_est"])) {
                continue;
            }

            $validatedItems[$itemId] = [
                'id' => $itemId,
                'qty' => $qty,
                'unit_price_est' => $unitPriceEst,
            ];
        }

        if (! empty($messages)) {
            throw ValidationException::withMessages($messages);
        }

        return $validatedItems;
    }
}

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
use App\Services\Inventory\InventorySelectionService;
use App\Support\BranchContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        Gate::forUser($actor)->authorize('create', PurchaseRequest::class);
        $explicitBranchId = $data['branch_id'] ?? null;
        $branchId = BranchContext::getEffectiveBranchId($explicitBranchId);
        abort_unless($actor->isGlobalAdmin() || $actor->canAccessBranch($branchId), 403);

        return DB::transaction(function () use ($actor, $data, $branchId) {
            $validatedItems = $this->validateDraftItems($data['items'], $branchId);
            // Create the PR
            $pr = PurchaseRequest::create([
                'branch_id' => $branchId,
                'requested_by' => $actor->id,
                'status' => PurchaseRequestStatus::Draft,
                'estimated_total' => 0,
                'note' => $data['note'] ?? null,
            ]);

            // Add items
            $estimatedTotal = BigDecimal::zero();
            foreach ($validatedItems as $item) {
                $qty = $item['qty'];
                $unitPriceEst = $item['unit_price_est'];
                $lineTotalEst = BigDecimal::of($qty)->multipliedBy($unitPriceEst)->toScale(2, RoundingMode::HALF_UP);

                PurchaseRequestItem::create([
                    'purchase_request_id' => $pr->id,
                    ...array_intersect_key($item, array_flip(['inventory_item_id', 'inventory_stock_unit_id', 'inventory_item_variant_id', 'variation_description', 'sku'])),
                    'item_name' => $item['item_name'],
                    'qty' => $qty,
                    'unit_price_est' => $unitPriceEst,
                    'line_total_est' => (string) $lineTotalEst,
                ]);

                $estimatedTotal = $estimatedTotal->plus($lineTotalEst);
            }

            // Update estimated total
            $pr->update(['estimated_total' => (string) $estimatedTotal]);

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

        return DB::transaction(function () use ($pr, $actor, $data) {
            $pr = PurchaseRequest::whereKey($pr->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $pr);
            $validatedItems = isset($data['items']) && is_array($data['items'])
                ? $this->validateDraftItems($data['items'], $pr->branch_id, $pr)
                : null;
            // Update note if provided
            if (isset($data['note'])) {
                $pr->update(['note' => $data['note']]);
            }

            // Update items if provided
            if ($validatedItems !== null) {
                // Delete existing items
                $pr->items()->delete();

                // Add new items
                $estimatedTotal = BigDecimal::zero();
                foreach ($validatedItems as $item) {
                    $qty = $item['qty'];
                    $unitPriceEst = $item['unit_price_est'];
                    $lineTotalEst = BigDecimal::of($qty)->multipliedBy($unitPriceEst)->toScale(2, RoundingMode::HALF_UP);

                    PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        ...array_intersect_key($item, array_flip(['inventory_item_id', 'inventory_stock_unit_id', 'inventory_item_variant_id', 'variation_description', 'sku'])),
                        'item_name' => $item['item_name'],
                        'qty' => $qty,
                        'unit_price_est' => $unitPriceEst,
                        'line_total_est' => (string) $lineTotalEst,
                    ]);

                    $estimatedTotal = $estimatedTotal->plus($lineTotalEst);
                }

                $pr->update(['estimated_total' => (string) $estimatedTotal]);
            }

            return $pr->fresh(['items', 'requester']);
        });
    }

    /**
     * Submit a draft purchase request.
     */
    public function submit(PurchaseRequest $pr, User $actor): PurchaseRequest
    {
        Gate::forUser($actor)->authorize('submit', $pr);
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
        Gate::forUser($actor)->authorize('approve', $pr);
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

        return DB::transaction(function () use ($pr, $actor, $validatedReviewedItems, $allocation) {
            // Update items with reviewed quantities/prices
            $approvedTotal = BigDecimal::zero();
            foreach ($pr->items as $item) {
                $reviewedItem = $validatedReviewedItems[$item->id] ?? null;
                if ($reviewedItem) {
                    $qty = $reviewedItem['qty'];
                    $unitPrice = $reviewedItem['unit_price_est'];
                    $lineTotal = BigDecimal::of($qty)->multipliedBy($unitPrice)->toScale(2, RoundingMode::HALF_UP);

                    $item->update([
                        'qty' => $qty,
                        'unit_price_est' => $unitPrice,
                        'line_total_est' => (string) $lineTotal,
                    ]);

                    $approvedTotal = $approvedTotal->plus($lineTotal);
                } else {
                    $approvedTotal = $approvedTotal->plus($item->line_total_est);
                }
            }

            $approvedTotal = (string) $approvedTotal;
            // Capital allocation linkage is optional when approving a PR.
            // If active allocation has enough balance, we post the debit and link it.
            $linkedAllocationId = null;
            if ($allocation && $approvedTotal > 0) {
                $availableBalance = $this->capitalService->availableBalance($allocation);
                if ($approvedTotal <= $availableBalance) {
                    $this->capitalService->createDebit(
                        $allocation,
                        $approvedTotal,
                        $actor,
                        $pr,
                        "Approved PR: {$pr->request_no}"
                    );

                    $linkedAllocationId = $allocation->id;
                }
            }

            // Update PR
            $pr->update([
                'status' => PurchaseRequestStatus::Approved,
                'reviewed_by' => $actor->id,
                'estimated_total' => $approvedTotal,
                'capital_allocation_id' => $linkedAllocationId,
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
        Gate::forUser($actor)->authorize('decline', $pr);
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
        Gate::forUser($actor)->authorize('convertToPo', $pr);
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
            $pr = PurchaseRequest::whereKey($pr->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('convertToPo', $pr);
            if (! \App\Models\Supplier::withoutBranchScope()->where('branch_id', $pr->branch_id)->whereKey($poData['supplier_id'])->exists()) {
                throw ValidationException::withMessages(['supplier_id' => 'Choose a supplier in this branch.']);
            }
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
                    'inventory_stock_unit_id' => $prItem->inventory_stock_unit_id,
                    'inventory_item_variant_id' => $prItem->inventory_item_variant_id,
                    'variation_description' => $prItem->variation_description,
                    'sku' => $prItem->sku,
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
    protected function validateDraftItems(array $items, int $branchId, ?PurchaseRequest $existing = null): array
    {
        $messages = [];
        $validatedItems = [];
        $owned = $existing?->items()->get()->keyBy('id') ?? collect();
        $seen = [];
        InventoryItem::withoutBranchScope()->whereIn('id', collect($items)->pluck('inventory_item_id')->filter()->unique())->orderBy('id')->lockForUpdate()->get();

        foreach ($items as $index => $item) {
            $inventoryItemId = isset($item['inventory_item_id']) ? (int) $item['inventory_item_id'] : null;
            $itemName = trim((string) ($item['item_name'] ?? ''));
            try {
                $qty = (string) BigDecimal::of((string) ($item['qty'] ?? 0))->toScale(2);
                $unitPriceEst = (string) BigDecimal::of((string) ($item['unit_price_est'] ?? 0))->toScale(2);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['items' => 'Enter valid decimal quantities and costs.']);
            }

            if ($qty <= 0) {
                $messages["items.{$index}.qty"] = 'Quantity must be greater than zero.';
            }

            if ($unitPriceEst < 0) {
                $messages["items.{$index}.unit_price_est"] = 'Estimated unit price cannot be negative.';
            }

            $identity = [];
            $old = null;
            if (! empty($item['id'])) {
                $old = $owned->get((int) $item['id']);
                if (! $old || isset($seen[$old->id])) {
                    throw ValidationException::withMessages(['items' => 'Invalid or repeated existing purchase request line.']);
                }
                $seen[$old->id] = true;
            }
            if ($inventoryItemId !== null) {
                $inventoryItem = InventoryItem::withoutGlobalScope(BranchScope::class)
                    ->where('branch_id', $branchId)
                    ->find($inventoryItemId);

                if (! $inventoryItem) {
                    $messages["items.{$index}.inventory_item_id"] = 'Selected inventory item is invalid for this branch.';

                    continue;
                }

                if ($old && (int) $old->inventory_item_id === $inventoryItemId && (int) $old->inventory_stock_unit_id === (int) ($item['inventory_stock_unit_id'] ?? 0)) {
                    $identity = $old->only(['inventory_stock_unit_id', 'inventory_item_variant_id', 'variation_description', 'sku']);
                    $itemName = $old->item_name;
                } else {
                    $selection = app(InventorySelectionService::class);
                    $unit = $selection->resolve($inventoryItemId, $item['inventory_stock_unit_id'] ?? null, $branchId);
                    if (isset($item['inventory_item_variant_id']) && (int) $item['inventory_item_variant_id'] !== (int) $unit->inventory_item_variant_id) {
                        throw ValidationException::withMessages(['items' => 'Variant does not match the selected stock identity.']);
                    }
                    $identity = $selection->snapshot($unit);
                    $itemName = $identity['item_name'];
                }
            }

            if ($itemName === '') {
                $messages["items.{$index}.item_name"] = 'Item name is required.';
            }

            if (isset($messages["items.{$index}.qty"]) || isset($messages["items.{$index}.unit_price_est"]) || isset($messages["items.{$index}.item_name"])) {
                continue;
            }

            $validatedItems[] = [
                ...$identity,
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

            try {
                $qty = (string) BigDecimal::of((string) ($item['qty'] ?? 0))->toScale(2);
                $unitPriceEst = (string) BigDecimal::of((string) ($item['unit_price_est'] ?? 0))->toScale(2);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['reviewedItems' => 'Enter valid decimal quantities and costs.']);
            }

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

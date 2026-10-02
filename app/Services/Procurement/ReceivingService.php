<?php

namespace App\Services\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
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
    public function receive(PurchaseOrder $po, array $receivedItems, User $actor, ?string $note = null, ?string $operationKey = null): GoodsReceipt
    {
        abort_unless($actor->can('procurement.receive') && ($actor->isGlobalAdmin() || $actor->canAccessBranch($po->branch_id)), 403);
        $normalized = [];
        foreach ($receivedItems as $input) {
            $id = (int) ($input['purchase_order_item_id'] ?? 0);
            if (! $id || isset($normalized[$id])) {
                throw ValidationException::withMessages(['items' => 'Choose each purchase order line once.']);
            }
            try {
                $qty = \Brick\Math\BigDecimal::of((string) ($input['qty_received'] ?? 0))->toScale(2);
                $cost = isset($input['unit_cost']) ? \Brick\Math\BigDecimal::of((string) $input['unit_cost'])->toScale(2) : null;
            } catch (\Throwable) {
                throw ValidationException::withMessages(['items' => 'Enter valid decimal quantities and costs with at most two decimal places.']);
            }
            if ($qty->isLessThanOrEqualTo(0) || $cost?->isNegative()) {
                throw ValidationException::withMessages(['items' => 'Receipt quantities must be positive and costs cannot be negative.']);
            }
            $normalized[$id] = ['qty' => (string) $qty, 'cost' => $cost === null ? null : (string) $cost];
        }
        if ($normalized === []) {
            throw ValidationException::withMessages(['items' => 'At least one item must be received.']);
        }
        ksort($normalized);
        $payloadHash = hash('sha256', json_encode([$normalized, $note], JSON_THROW_ON_ERROR));
        if ($operationKey === null || trim($operationKey) === '' || strlen($operationKey) > 200) {
            throw ValidationException::withMessages(['receipt' => 'A durable receipt operation key is required. Reuse it only when retrying the same delivery.']);
        }
        $key = hash('sha256', $po->id.':'.$operationKey);

        return DB::transaction(function () use ($po, $normalized, $actor, $note, $payloadHash, $key) {
            $po = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isGlobalAdmin() || $actor->canAccessBranch($po->branch_id), 403);
            if ($key && $existing = GoodsReceipt::where('operation_key', $key)->first()) {
                if (! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages(['items' => 'This receipt submission was already used with different quantities or costs. Reload before a new receipt.']);
                }

                return $existing->load(['items.inventoryItem', 'purchaseOrder', 'receiver']);
            }
            if (! in_array($po->status, [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived])) {
                throw ValidationException::withMessages(['status' => 'Can only receive goods for sent or partially received POs.']);
            }
            $lines = $po->items()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if (array_diff(array_keys($normalized), $lines->keys()->all())) {
                throw ValidationException::withMessages(['items' => 'A receipt line does not belong to this purchase order.']);
            }
            InventoryItem::withoutBranchScope()->whereIn('id', $lines->whereIn('id', array_keys($normalized))->pluck('inventory_item_id')->filter()->unique())->orderBy('id')->lockForUpdate()->get();
            $grn = GoodsReceipt::create([
                'branch_id' => $po->branch_id, 'purchase_order_id' => $po->id,
                'received_at' => now(), 'received_by' => $actor->id, 'note' => $note,
                'operation_key' => $key, 'payload_hash' => $payloadHash,
            ]);
            foreach ($normalized as $id => $input) {
                $line = $lines[$id];
                $qty = \Brick\Math\BigDecimal::of($input['qty']);
                $pending = \Brick\Math\BigDecimal::of($line->qty_ordered)->minus($line->qty_received);
                if ($qty->isGreaterThan($pending)) {
                    throw ValidationException::withMessages(['qty' => "Cannot receive {$qty} for '{$line->item_name}'. Only {$pending} pending."]);
                }
                $cost = \Brick\Math\BigDecimal::of($input['cost'] ?? $line->unit_cost ?? 0)->toScale(2);
                if ($cost->isNegative()) {
                    throw ValidationException::withMessages(['cost' => 'Receipt cost cannot be negative.']);
                }
                $unit = $line->inventory_item_id ? app(\App\Services\Inventory\InventorySelectionService::class)->resolve($line->inventory_item_id, $line->inventory_stock_unit_id, $po->branch_id) : null;
                if (($line->inventory_item_variant_id !== null && (int) $line->inventory_item_variant_id !== (int) $unit?->inventory_item_variant_id) || (! $unit && $line->inventory_stock_unit_id)) {
                    throw ValidationException::withMessages(['inventory' => 'Purchase identity requires reconciliation.']);
                }
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $grn->id, 'purchase_order_item_id' => $line->id,
                    'inventory_item_id' => $line->inventory_item_id,
                    'inventory_stock_unit_id' => $unit?->id,
                    'inventory_item_variant_id' => $unit?->inventory_item_variant_id,
                    'item_name' => $line->item_name,
                    'variation_description' => $line->variation_description,
                    'sku' => $line->sku ?? $unit?->sku,
                    'qty_received' => (string) $qty, 'unit_cost' => (string) $cost,
                    'line_total' => (string) $qty->multipliedBy($cost)->toScale(2, \Brick\Math\RoundingMode::HALF_UP),
                ]);
                $line->update(['qty_received' => (string) \Brick\Math\BigDecimal::of($line->qty_received)->plus($qty)]);
                if ($unit) {
                    $this->stockService->receive($unit, (string) $qty, (string) $cost, "Received via GRN: {$grn->grn_no}", $actor, $grn);
                }
            }
            $this->updatePurchaseOrderStatus($po);
            DB::afterCommit(fn () => $this->notifyOfGoodsReceived($grn, $po));

            return $grn->load(['items.inventoryItem', 'purchaseOrder', 'receiver']);
        });
    }

    /**
     * Update PO status based on received quantities.
     */
    protected function updatePurchaseOrderStatus(PurchaseOrder $po): void
    {
        $po->refresh();

        if (! $po->items()->whereColumn('qty_received', '<', 'qty_ordered')->exists()) {
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

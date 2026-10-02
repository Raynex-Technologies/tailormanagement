<?php

namespace App\Services\Orders;

use App\Models\InventoryItem;
use App\Models\InventoryStockUnit;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderLine;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

class OrderInventoryPreflight
{
    /** Normalize prospective snapshots and retain an aggregate availability pass. */
    public function validate(array $lines, ?Order $order, int $branchId, bool $lock = false): array
    {
        if ($order && (int) $order->branch_id !== $branchId) {
            $this->fail('Order branch does not match inventory selection.');
        }
        $existing = $order ? $order->lines()->get()->keyBy('id') : collect();
        $submittedIds = collect($lines)->pluck('id')->filter();
        if ($submittedIds->unique()->count() !== $submittedIds->count() || $submittedIds->diff($existing->keys())->isNotEmpty()) {
            $this->fail('Every existing line must belong to this Order and appear only once.');
        }
        $itemIds = collect($lines)->pluck('inventory_item_id')->merge($existing->pluck('inventory_item_id'))->filter()->unique()->sort()->values();
        $items = InventoryItem::withoutBranchScope()->whereIn('id', $itemIds)->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get()->keyBy('id');
        $requested = [];
        $units = [];
        $selection = app(OrderInventorySelectionService::class);
        foreach ($lines as &$line) {
            $old = $existing->get($line['id'] ?? null);
            if (empty($line['inventory_item_id'])) {
                if (! empty($line['inventory_stock_unit_id']) || ! empty($line['inventory_item_variant_id'])) {
                    $this->fail('Inventory identity requires its product.');
                }

                continue;
            }
            $item = $items->get($line['inventory_item_id']);
            if (! $item || (int) $item->branch_id !== $branchId) {
                $this->fail('Inventory must belong to the Order branch.');
            }
            $same = $old && (int) $old->inventory_item_id === (int) $item->id
                && (int) $old->inventory_stock_unit_id === (int) ($line['inventory_stock_unit_id'] ?? null);
            $quantity = BigDecimal::of((string) $line['qty'])->toScale(2);
            if (! $quantity->isPositive()) {
                $this->fail('Order quantity must be positive.');
            }
            if ($same && ! $old->inventory_stock_unit_id && $item->variant_mode !== 'simple') {
                if (! $quantity->isEqualTo($old->qty)) {
                    $this->fail('Legacy ambiguous stock needs reconciliation before changing quantity.');
                }
                $line = [...$line, 'inventory_item_variant_id' => $old->inventory_item_variant_id,
                    'variation_description' => $old->variation_description, 'sku' => $old->sku, 'item_name' => $old->item_name];

                continue;
            }
            $unit = $same && $quantity->isLessThanOrEqualTo($old->qty)
                ? ($old->inventory_stock_unit_id ? InventoryStockUnit::where('inventory_item_id', $item->id)->findOrFail($old->inventory_stock_unit_id) : app(\App\Services\Inventory\StockUnitResolver::class)->forItem($item))
                : $selection->resolve($item->id, ($line['inventory_stock_unit_id'] ?? null) ?: null, $branchId);
            if (isset($line['inventory_item_variant_id']) && (int) $line['inventory_item_variant_id'] !== (int) $unit->inventory_item_variant_id) {
                $this->fail('Variant does not match the selected stock unit.');
            }
            $snapshot = $selection->snapshot($unit);
            if ($same) {
                $snapshot = ['inventory_item_id' => $old->inventory_item_id, 'inventory_stock_unit_id' => $old->inventory_stock_unit_id,
                    'inventory_item_variant_id' => $old->inventory_item_variant_id, 'variation_description' => $old->variation_description,
                    'sku' => $old->sku, 'item_name' => $old->item_name];
            }
            $line = [...$line, ...$snapshot];
            $units[$unit->id] = $unit;
            $requested[$unit->id] = ($requested[$unit->id] ?? BigDecimal::zero())->plus($quantity);
        }
        unset($line);
        $issued = [];
        if ($existing->isNotEmpty()) {
            $movements = InventoryTransaction::withoutBranchScope()->where('reference_type', (new OrderLine)->getMorphClass())
                ->whereIn('reference_id', $existing->keys())->whereIn('type', ['issue', 'return'])->get();
            foreach ($movements as $movement) {
                $id = $movement->inventory_stock_unit_id;
                if (! $id) {
                    $id = collect($units)->first(fn ($u) => ! $u->inventory_item_variant_id && (int) $u->inventory_item_id === (int) $movement->inventory_item_id)?->id;
                }
                if ($id) {
                    $issued[$id] = ($issued[$id] ?? BigDecimal::zero())->minus((string) $movement->qty);
                }
            }
        }
        ksort($units);
        foreach ($units as $id => $unit) {
            $stock = $unit->stock()->when($lock, fn ($q) => $q->lockForUpdate())->first();
            if (! $stock || (int) $stock->branch_id !== $branchId) {
                $this->fail('Stock balance is not prepared for this branch.');
            }
            $held = $issued[$id] ?? BigDecimal::zero();
            if ($held->isNegative()) {
                $this->fail('Order inventory movements require reconciliation.');
            }
            $available = BigDecimal::of((string) $stock->qty_on_hand)->minus((string) $stock->qty_reserved)->plus($held);
            if ($requested[$id]->isGreaterThan($available)) {
                $this->fail($unit->sku.' has only '.$available.' available for this Order.');
            }
        }

        return $lines;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['lines' => $message]);
    }
}

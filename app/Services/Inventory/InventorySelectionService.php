<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryStockUnit;
use Illuminate\Validation\ValidationException;

/** Operational identity selection; callers retain their own workflow permissions. */
class InventorySelectionService
{
    public function resolve(int $itemId, ?int $unitId, int $branchId): InventoryStockUnit
    {
        $item = InventoryItem::withoutBranchScope()->where('branch_id', $branchId)->findOrFail($itemId);
        $resolver = app(StockUnitResolver::class);
        $resolver->authorizeItem($item);
        $unit = $unitId ? InventoryStockUnit::where('inventory_item_id', $item->id)->findOrFail($unitId) : $resolver->forItem($item);
        if (! $resolver->isSellable($unit)) {
            throw ValidationException::withMessages(['inventory' => 'Requires attention: select an active, allocated stock identity.']);
        }

        return $unit->load(['item', 'variant.selectedValues.option', 'stock']);
    }

    public function snapshot(InventoryStockUnit $unit): array
    {
        return [
            'inventory_item_id' => $unit->inventory_item_id,
            'inventory_item_variant_id' => $unit->inventory_item_variant_id,
            'inventory_stock_unit_id' => $unit->id,
            'item_name' => $unit->item->name,
            'variation_description' => $unit->variant?->display_name,
            'sku' => $unit->sku,
        ];
    }
}

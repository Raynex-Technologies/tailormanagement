<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryItemVariant;
use App\Models\InventoryStockUnit;
use App\Models\InventoryStockUnitBarcode;
use App\Support\BranchContext;
use Illuminate\Validation\ValidationException;

class StockUnitResolver
{
    public function forItem(InventoryItem $item): InventoryStockUnit
    {
        $item = InventoryItem::withoutBranchScope()->findOrFail($item->id);
        $this->authorizeItem($item);
        if ($item->variant_mode !== 'simple') {
            $this->fail('Select the exact variant before changing stock for this product.');
        }
        $unit = InventoryStockUnit::where('inventory_item_id', $item->id)->whereNull('inventory_item_variant_id')->first();
        if (! $unit) {
            $this->fail('Stock identity is not prepared. Run inventory:stock-units:backfill before stock operations.');
        }

        return $unit;
    }

    public function forVariant(InventoryItemVariant $variant): InventoryStockUnit
    {
        $variant = InventoryItemVariant::findOrFail($variant->id);
        $item = InventoryItem::withoutBranchScope()->findOrFail($variant->inventory_item_id);
        $this->authorizeItem($item);
        $unit = InventoryStockUnit::where('inventory_item_id', $item->id)->where('inventory_item_variant_id', $variant->id)->first();
        if (! $unit) {
            $this->fail('Variant stock identity requires review/backfill.');
        }

        return $unit;
    }

    public function bySku(string $sku): ?InventoryStockUnit
    {
        $unit = InventoryStockUnit::where('sku', $sku)->first();

        // Database collations may be case-insensitive. Identity lookup is exact.
        return $unit && $unit->sku === $sku && $this->isSellable($unit) ? $unit : null;
    }

    public function byBarcode(string $barcode): ?InventoryStockUnit
    {
        $alias = InventoryStockUnitBarcode::where('barcode', $barcode)->with('stockUnit')->first();

        return $alias && $alias->barcode === $barcode && $this->isSellable($alias->stockUnit) ? $alias->stockUnit : null;
    }

    public function isSellable(InventoryStockUnit $unit): bool
    {
        $item = InventoryItem::query()->find($unit->inventory_item_id);
        if (! $item || ! $item->is_active || ! $unit->is_active || $unit->allocation_status !== 'ready') {
            return false;
        }
        try {
            $this->authorizeItem($item);
        } catch (ValidationException) {
            return false;
        }
        if ($unit->inventory_item_variant_id) {
            return $item->variant_mode === 'variants' && InventoryItemVariant::whereKey($unit->inventory_item_variant_id)->where('inventory_item_id', $item->id)->where('is_active', true)->exists();
        }

        return $item->variant_mode === 'simple';
    }

    public function authorizeItem(InventoryItem $item): void
    {
        $user = auth()->user();
        if ($user && ! $user->isGlobalAdmin() && (int) $item->branch_id !== (int) $user->branch_id) {
            $this->fail('Stock identity belongs to another branch.');
        }
        if ($user?->isGlobalAdmin() && BranchContext::id() !== null && (int) $item->branch_id !== (int) BranchContext::id()) {
            $this->fail('Select the stock identity branch first.');
        }
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['stock_unit' => $message]);
    }
}

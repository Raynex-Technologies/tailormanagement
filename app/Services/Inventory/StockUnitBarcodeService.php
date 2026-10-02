<?php

namespace App\Services\Inventory;

use App\Models\InventoryStockUnit;
use App\Models\InventoryStockUnitBarcode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockUnitBarcodeService
{
    public function assign(InventoryStockUnit $unit, string $barcode, bool $primary = true): InventoryStockUnitBarcode
    {
        abort_unless(auth()->user()?->can('inventory.items.manage'), 403);
        if ($barcode === '' || trim($barcode) !== $barcode || strlen($barcode) > 191) {
            throw ValidationException::withMessages(['barcode' => 'Enter an exact nonempty barcode without surrounding whitespace.']);
        }

        return DB::transaction(function () use ($unit, $barcode, $primary) {
            $unit = InventoryStockUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            app(StockUnitResolver::class)->authorizeItem(\App\Models\InventoryItem::withoutBranchScope()->findOrFail($unit->inventory_item_id));
            $existing = InventoryStockUnitBarcode::where('barcode', $barcode)->first();
            if ($existing && ((int) $existing->inventory_stock_unit_id !== $unit->id || $existing->barcode !== $barcode)) {
                throw ValidationException::withMessages(['barcode' => 'Barcode is already assigned. No SKU or barcode was renamed.']);
            }
            if ($primary) {
                $unit->barcodes()->where('is_primary', true)->update(['is_primary' => false]);
            }

            return $unit->barcodes()->updateOrCreate(['barcode' => $barcode], ['is_primary' => $primary]);
        });
    }
}

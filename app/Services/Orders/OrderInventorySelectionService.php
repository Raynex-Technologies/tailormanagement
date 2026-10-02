<?php

namespace App\Services\Orders;

use App\Models\InventoryStockUnit;
use App\Services\Inventory\InventorySelectionService;
use Illuminate\Validation\ValidationException;

class OrderInventorySelectionService extends InventorySelectionService
{
    public function resolve(int $itemId, ?int $unitId, int $branchId): InventoryStockUnit
    {
        $unit = parent::resolve($itemId, $unitId, $branchId);
        if ($unit->selling_price === null || ! is_numeric($unit->selling_price) || (float) $unit->selling_price < 0) {
            throw ValidationException::withMessages(['inventory' => 'Select an active, allocated variation with a valid selling price.']);
        }

        return $unit;
    }
}

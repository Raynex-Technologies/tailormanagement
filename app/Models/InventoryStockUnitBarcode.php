<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockUnitBarcode extends Model
{
    protected $fillable = ['inventory_stock_unit_id', 'barcode', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryStockUnit::class, 'inventory_stock_unit_id');
    }
}

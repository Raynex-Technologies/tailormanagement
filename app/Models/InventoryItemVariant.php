<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItemVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'name',
        'size',
        'color',
        'sku',
        'price_delta',
        'stock_qty',
        'option_values',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'stock_qty' => 'decimal:2',
            'option_values' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

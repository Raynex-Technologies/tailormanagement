<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontProductComboItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'storefront_product_combo_id',
        'inventory_item_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(StorefrontProductCombo::class, 'storefront_product_combo_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

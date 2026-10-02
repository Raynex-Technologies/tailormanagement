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
        'combination_key',
        'name',
        'size',
        'color',
        'sku',
        'price_delta',
        'stock_qty',
        'option_values',
        'is_active',
    ];

    public function stockUnit(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(InventoryStockUnit::class, 'inventory_item_variant_id');
    }

    public function selectedValues()
    {
        return $this->belongsToMany(InventoryItemOptionValue::class, 'inventory_item_variant_option_value');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->selectedValues->sortBy(fn ($value) => $value->option->sort_order)->pluck('name')->implode(' / ') ?: $this->name;
    }

    protected static function booted(): void
    {
        static::updating(function (self $variant) {
            if ($variant->isDirty('inventory_item_id') && $variant->stockUnit()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['variant' => 'A variant stock identity cannot be moved to another product.']);
            }
        });
        static::updated(function (self $variant) {
            if ($variant->wasChanged('is_active') && ! $variant->is_active) {
                $variant->stockUnit()->update(['is_active' => false]);
            }
        });
    }

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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'inventory_item_id',
        'inventory_stock_unit_id',
        'inventory_item_variant_id',
        'variation_description',
        'sku',
        'item_name',
        'qty',
        'unit_price_est',
        'line_total_est',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price_est' => 'decimal:2',
            'line_total_est' => 'decimal:2',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function getSelectionWarningAttribute(): ?string
    {
        if ($this->inventory_stock_unit_id) {
            return $this->stockUnit && app(\App\Services\Inventory\StockUnitResolver::class)->isSellable($this->stockUnit) ? null : 'Requires attention';
        }

        return $this->inventoryItem?->variant_mode === 'variants' ? 'Requires attention: historical selection needs review' : null;
    }

    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryStockUnit::class, 'inventory_stock_unit_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}

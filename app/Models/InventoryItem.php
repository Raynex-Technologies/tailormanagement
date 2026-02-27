<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryItem extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'inventory_category_id',
        'inventory_unit_id',
        'sku',
        'name',
        'unit',
        'reorder_level',
        'default_buy_price',
        'default_sell_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:2',
            'default_buy_price' => 'decimal:2',
            'default_sell_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    public function stock(): HasOne
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function purchaseRequestItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function stockRequestItems(): HasMany
    {
        return $this->hasMany(OrderStockRequestItem::class);
    }

    /**
     * Get current available quantity (on_hand - reserved).
     */
    public function getAvailableQtyAttribute(): float
    {
        return ($this->stock?->qty_on_hand ?? 0) - ($this->stock?->qty_reserved ?? 0);
    }

    /**
     * Check if stock is below reorder level.
     */
    public function isLowStock(): bool
    {
        return ($this->stock?->qty_on_hand ?? 0) <= $this->reorder_level;
    }
}

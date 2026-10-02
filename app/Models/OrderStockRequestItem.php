<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderStockRequestItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_stock_request_id',
        'inventory_item_id',
        'inventory_stock_unit_id',
        'inventory_item_variant_id',
        'variation_description',
        'sku',
        'item_name',
        'qty_requested',
        'qty_approved',
        'qty_issued',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'qty_requested' => 'decimal:2',
            'qty_approved' => 'decimal:2',
            'qty_issued' => 'decimal:2',
        ];
    }

    // ============================================
    // Relationships
    // ============================================

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(OrderStockRequest::class, 'order_stock_request_id');
    }

    public function getSelectionWarningAttribute(): ?string
    {
        if ($this->inventory_stock_unit_id) {
            return $this->stockUnit && app(\App\Services\Inventory\StockUnitResolver::class)->isSellable($this->stockUnit) ? null : 'Requires attention';
        }

        return $this->inventoryItem?->variant_mode === 'variants' ? 'Requires attention: historical selection needs review' : null;
    }

    public function getAvailableStockAttribute(): string
    {
        $stock = $this->inventory_stock_unit_id ? $this->stockUnit?->stock : ($this->inventoryItem?->variant_mode === 'simple' ? $this->inventoryItem?->stock : null);

        return (string) \Brick\Math\BigDecimal::of($stock?->qty_on_hand ?? 0)->minus($stock?->qty_reserved ?? 0);
    }

    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryStockUnit::class, 'inventory_stock_unit_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    // ============================================
    // Helpers
    // ============================================

    /**
     * Get remaining quantity to be issued.
     */
    public function getRemainingToIssueAttribute(): float
    {
        return max(0, (float) $this->qty_approved - (float) $this->qty_issued);
    }

    /**
     * Check if this item is fully issued.
     */
    public function isFullyIssued(): bool
    {
        return (float) $this->qty_issued >= (float) $this->qty_approved;
    }

    /**
     * Check if item can still be issued more quantity.
     */
    public function canBeIssued(): bool
    {
        return $this->remaining_to_issue > 0;
    }
}

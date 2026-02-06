<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStockRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_stock_request_id',
        'inventory_item_id',
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

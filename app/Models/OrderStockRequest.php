<?php

namespace App\Models;

use App\Enums\StockRequestStatus;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderStockRequest extends Model
{
    use BranchScoped, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'order_id',
        'requested_by',
        'handled_by',
        'status',
        'note',
        'handler_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockRequestStatus::class,
        ];
    }

    // ============================================
    // Relationships
    // ============================================

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderStockRequestItem::class);
    }

    // ============================================
    // Scopes
    // ============================================

    /**
     * Filter by status.
     */
    public function scopeStatus(Builder $query, $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        if ($status instanceof StockRequestStatus) {
            return $query->where('status', $status);
        }

        return $query->where('status', $status);
    }

    /**
     * Filter by order number or customer name.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->whereHas('order', function ($oq) use ($term) {
                $oq->where('order_no', 'like', "%{$term}%")
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
            })
            ->orWhereHas('requester', function ($rq) use ($term) {
                $rq->where('name', 'like', "%{$term}%");
            });
        });
    }

    /**
     * Get only pending requests (requested status).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', StockRequestStatus::Requested);
    }

    /**
     * Get only approved requests awaiting fulfillment.
     */
    public function scopeAwaitingFulfillment(Builder $query): Builder
    {
        return $query->where('status', StockRequestStatus::Approved);
    }

    // ============================================
    // Helpers
    // ============================================

    /**
     * Check if request can be reviewed (approved/declined).
     */
    public function canBeReviewed(): bool
    {
        return $this->status->canBeReviewed();
    }

    /**
     * Check if request can be fulfilled.
     */
    public function canBeFulfilled(): bool
    {
        return $this->status->canBeFulfilled();
    }

    /**
     * Check if all items are fully issued.
     */
    public function isFullyIssued(): bool
    {
        return $this->items->every(function ($item) {
            return (float) $item->qty_issued >= (float) $item->qty_approved;
        });
    }

    /**
     * Get total requested quantity across all items.
     */
    public function getTotalRequestedAttribute(): float
    {
        return $this->items->sum('qty_requested');
    }

    /**
     * Get total approved quantity across all items.
     */
    public function getTotalApprovedAttribute(): float
    {
        return $this->items->sum('qty_approved');
    }

    /**
     * Get total issued quantity across all items.
     */
    public function getTotalIssuedAttribute(): float
    {
        return $this->items->sum('qty_issued');
    }
}

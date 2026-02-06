<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Models\Concerns\BranchScoped;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use BranchScoped, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_no)) {
                $order->order_no = DocNumber::order();
            }
        });

        // Set completed_at when status changes to Completed
        static::updating(function (Order $order) {
            if ($order->isDirty('status')) {
                $newStatus = $order->status;
                if ($newStatus === OrderStatus::Completed && $order->completed_at === null) {
                    $order->completed_at = now();
                }
            }
        });
    }

    protected $fillable = [
        'branch_id',
        'order_no',
        'customer_id',
        'assigned_tailor_id',
        'status',
        'due_date',
        'completed_at',
        'priority',
        'notes',
        'subtotal',
        'discount',
        'total',
        'payment_status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'priority' => Priority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    // ============================================
    // Relationships
    // ============================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTailor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_tailor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(OrderComment::class);
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(OrderWatcher::class);
    }

    public function stockRequests(): HasMany
    {
        return $this->hasMany(OrderStockRequest::class);
    }

    public function deliveryNote(): HasOne
    {
        return $this->hasOne(DeliveryNote::class);
    }

    // ============================================
    // Accessors
    // ============================================

    /**
     * Get total amount paid.
     */
    public function getPaidAmountAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * Alias for paid_amount (backwards compat).
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->paid_amount;
    }

    /**
     * Get balance due.
     */
    public function getBalanceDueAttribute(): float
    {
        return max(0, (float) $this->total - $this->paid_amount);
    }

    /**
     * Alias for balance_due (for SMS templates).
     */
    public function getBalanceAmountAttribute(): float
    {
        return $this->balance_due;
    }

    /**
     * Compute payment status based on payments.
     */
    public function getComputedPaymentStatusAttribute(): PaymentStatus
    {
        $paidAmount = $this->paid_amount;
        $total = (float) $this->total;

        if ($paidAmount <= 0) {
            return PaymentStatus::Unpaid;
        }

        if ($paidAmount >= $total) {
            return PaymentStatus::Paid;
        }

        return PaymentStatus::Partial;
    }

    // ============================================
    // Scopes
    // ============================================

    /**
     * Search orders by order_no, customer name, or customer phone.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('order_no', 'like', "%{$term}%")
                ->orWhereHas('customer', function ($cq) use ($term) {
                    $cq->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
        });
    }

    /**
     * Filter by status.
     */
    public function scopeStatus(Builder $query, $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        if ($status instanceof OrderStatus) {
            return $query->where('status', $status);
        }

        return $query->where('status', $status);
    }

    /**
     * Filter by assigned tailor.
     */
    public function scopeAssignedTo(Builder $query, ?int $tailorId): Builder
    {
        if (empty($tailorId)) {
            return $query;
        }

        return $query->where('assigned_tailor_id', $tailorId);
    }

    /**
     * Filter by date range.
     */
    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope for "In Progress" group on Order Board.
     * Includes: in_progress, ready
     */
    public function scopeInProgressGroup(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OrderStatus::InProgress,
            OrderStatus::Ready,
        ]);
    }

    /**
     * Scope for "Completed" group on Order Board.
     * Includes: delivered, completed
     */
    public function scopeCompletedGroup(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OrderStatus::Delivered,
            OrderStatus::Completed,
        ]);
    }

    /**
     * Scope for "New" orders on Order Board.
     */
    public function scopeNewOrders(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::New);
    }

    /**
     * Scope to get only orders assigned to a specific tailor.
     * Used for tailor role filtering.
     */
    public function scopeForTailor(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_tailor_id', $userId);
    }

    // ============================================
    // Helpers
    // ============================================

    /**
     * Check if order is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && ! in_array($this->status, [
            OrderStatus::Delivered,
            OrderStatus::Completed,
            OrderStatus::Cancelled,
        ]);
    }

    /**
     * Check if status can transition forward.
     */
    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        $currentStatus = $this->status;

        // Define allowed transitions (forward only + cancel from any)
        $transitions = [
            OrderStatus::New->value => [OrderStatus::InProgress, OrderStatus::Cancelled],
            OrderStatus::InProgress->value => [OrderStatus::Ready, OrderStatus::Cancelled],
            OrderStatus::Ready->value => [OrderStatus::Delivered, OrderStatus::Cancelled],
            OrderStatus::Delivered->value => [OrderStatus::Completed, OrderStatus::Cancelled],
            OrderStatus::Completed->value => [], // Terminal state
            OrderStatus::Cancelled->value => [], // Terminal state
        ];

        $allowed = $transitions[$currentStatus->value] ?? [];

        return in_array($newStatus, $allowed);
    }

    /**
     * Get next valid statuses for transition.
     */
    public function getNextStatuses(): array
    {
        $transitions = [
            OrderStatus::New->value => [OrderStatus::InProgress, OrderStatus::Cancelled],
            OrderStatus::InProgress->value => [OrderStatus::Ready, OrderStatus::Cancelled],
            OrderStatus::Ready->value => [OrderStatus::Delivered, OrderStatus::Cancelled],
            OrderStatus::Delivered->value => [OrderStatus::Completed],
            OrderStatus::Completed->value => [],
            OrderStatus::Cancelled->value => [],
        ];

        return $transitions[$this->status->value] ?? [];
    }

    /**
     * Check if delivery note can be created.
     */
    public function canCreateDeliveryNote(): bool
    {
        // Only allow delivery note for delivered or completed orders
        // and if one doesn't already exist
        return in_array($this->status, [OrderStatus::Delivered, OrderStatus::Completed])
            && ! $this->deliveryNote()->exists();
    }

    /**
     * Recalculate totals from lines.
     */
    public function recalculateTotals(): void
    {
        $subtotal = $this->lines()->sum('line_total');
        $this->subtotal = $subtotal;
        $this->total = $subtotal - ($this->discount ?? 0);
        $this->save();
    }
}

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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Order extends Model
{
    use BranchScoped, HasFactory, SoftDeletes;

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

        static::created(function (Order $order) {
            Invoice::syncFromOrder($order, auth()->id());
        });

        static::updated(function (Order $order) {
            Invoice::syncFromOrder($order, auth()->id());
        });
    }

    protected $fillable = [
        'branch_id',
        'order_no',
        'customer_id',
        'assigned_tailor_id',
        'status',
        'order_date',
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
            'order_date' => 'date',
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

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function orderExpenses(): HasMany
    {
        return $this->hasMany(OrderExpense::class);
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

        return $query->where(function (Builder $q) use ($tailorId) {
            $q->where('assigned_tailor_id', $tailorId)
                ->orWhereHas('lines', function (Builder $lineQuery) use ($tailorId) {
                    $lineQuery->where('assigned_tailor_id', $tailorId);
                });
        });
    }

    /**
     * Filter by date range.
     */
    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        $orderDateColumn = $query->qualifyColumn('order_date');
        $createdAtColumn = $query->qualifyColumn('created_at');

        if ($from) {
            $query->where(function (Builder $dateQuery) use ($from, $orderDateColumn, $createdAtColumn) {
                $dateQuery->whereDate($orderDateColumn, '>=', $from)
                    ->orWhere(function (Builder $legacyQuery) use ($from, $orderDateColumn, $createdAtColumn) {
                        // Keep legacy rows (without order_date) filterable.
                        $legacyQuery->whereNull($orderDateColumn)
                            ->whereDate($createdAtColumn, '>=', $from);
                    });
            });
        }

        if ($to) {
            $query->where(function (Builder $dateQuery) use ($to, $orderDateColumn, $createdAtColumn) {
                $dateQuery->whereDate($orderDateColumn, '<=', $to)
                    ->orWhere(function (Builder $legacyQuery) use ($to, $orderDateColumn, $createdAtColumn) {
                        // Keep legacy rows (without order_date) filterable.
                        $legacyQuery->whereNull($orderDateColumn)
                            ->whereDate($createdAtColumn, '<=', $to);
                    });
            });
        }

        return $query;
    }

    /**
     * Scope for "In Progress" group on Order Board.
     * Includes: in_progress
     */
    public function scopeInProgressGroup(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::InProgress);
    }

    /**
     * Scope for "Ready" group on Order Board.
     * Includes: ready
     */
    public function scopeReadyGroup(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::Ready);
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
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('assigned_tailor_id', $userId)
                ->orWhereHas('lines', function (Builder $lineQuery) use ($userId) {
                    $lineQuery->where('assigned_tailor_id', $userId);
                });
        });
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
        // Delivery is only allowed when the order is fully settled.
        if ($newStatus === OrderStatus::Delivered && $this->hasOutstandingBalance()) {
            return false;
        }

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

        $next = $transitions[$this->status->value] ?? [];

        if ($this->hasOutstandingBalance()) {
            $next = array_filter($next, fn (OrderStatus $status) => $status !== OrderStatus::Delivered);
        }

        return array_values($next);
    }

    /**
     * Check if delivery note can be created.
     */
    public function canCreateDeliveryNote(): bool
    {
        // Only allow delivery note for delivered or completed orders
        // and if one doesn't already exist and the order has no balance due
        return in_array($this->status, [OrderStatus::Delivered, OrderStatus::Completed])
            && ! $this->hasOutstandingBalance()
            && ! $this->deliveryNote()->exists();
    }

    /**
     * Check whether the order still has balance due.
     */
    public function hasOutstandingBalance(): bool
    {
        return $this->balance_due > 0.00001;
    }

    /**
     * Check whether this order is assigned to the given tailor either directly
     * at order level or per any line item.
     */
    public function isAssignedToTailor(int $tailorId): bool
    {
        if ((int) $this->assigned_tailor_id === $tailorId) {
            return true;
        }

        if ($this->relationLoaded('lines')) {
            return $this->lines->contains(fn (OrderLine $line) => (int) $line->assigned_tailor_id === $tailorId);
        }

        return $this->lines()->where('assigned_tailor_id', $tailorId)->exists();
    }

    /**
     * Get unique tailor names involved in this order from both order-level and line-level assignment.
     */
    public function involvedTailorNames(): Collection
    {
        $tailorNames = collect();

        $orderTailorName = $this->relationLoaded('assignedTailor')
            ? $this->assignedTailor?->name
            : $this->assignedTailor()->value('name');

        if (filled($orderTailorName)) {
            $tailorNames->push($orderTailorName);
        }

        if ($this->relationLoaded('lines')) {
            $this->lines->loadMissing('assignedTailor:id,name');

            $lineTailorNames = $this->lines
                ->pluck('assignedTailor.name');
        } else {
            $lineTailorNames = $this->lines()
                ->with('assignedTailor:id,name')
                ->get()
                ->pluck('assignedTailor.name');
        }

        return $tailorNames
            ->merge($lineTailorNames)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Check whether any order line has an explicit tailor assignment.
     */
    public function hasPerItemTailorAssignments(): bool
    {
        if ($this->relationLoaded('lines')) {
            return $this->lines->contains(
                fn (OrderLine $line) => filled($line->assigned_tailor_id)
            );
        }

        return $this->lines()->whereNotNull('assigned_tailor_id')->exists();
    }

    /**
     * Check whether the order has any tailor assignment at order level or line level.
     */
    public function hasTailorAssignments(): bool
    {
        return filled($this->assigned_tailor_id) || $this->hasPerItemTailorAssignments();
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

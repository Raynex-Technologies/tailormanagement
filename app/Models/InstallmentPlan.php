<?php

namespace App\Models;

use App\Enums\InstallmentFrequency;
use App\Enums\InstallmentPlanStatus;
use App\Models\Concerns\BranchScoped;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPlan extends Model
{
    use BranchScoped, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (InstallmentPlan $plan) {
            if (blank($plan->plan_no)) {
                $plan->plan_no = DocNumber::installmentPlan();
            }
        });
    }

    protected $fillable = [
        'branch_id',
        'plan_no',
        'customer_id',
        'package_id',
        'package_name',
        'package_price',
        'package_duration_value',
        'package_duration_unit',
        'installments_count',
        'payment_frequency',
        'installment_amount',
        'start_date',
        'first_due_date',
        'maturity_date',
        'status',
        'notes',
        'created_by',
        'last_paid_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'package_price' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'installments_count' => 'integer',
            'payment_frequency' => InstallmentFrequency::class,
            'status' => InstallmentPlanStatus::class,
            'start_date' => 'date',
            'first_due_date' => 'date',
            'maturity_date' => 'date',
            'last_paid_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class)->orderBy('installment_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0, (float) $this->package_price - $this->total_paid);
    }

    public function getCompletionPercentageAttribute(): float
    {
        if ((float) $this->package_price <= 0.0) {
            return 0;
        }

        return min(100, round(($this->total_paid / (float) $this->package_price) * 100, 2));
    }

    public function getNextDueDateAttribute(): ?string
    {
        return $this->schedules()
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date')
            ->value('due_date');
    }

    public function isOverdue(): bool
    {
        return $this->schedules()
            ->whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->exists();
    }

    public function durationLabel(): string
    {
        $unit = str($this->package_duration_unit)->singular()->toString();

        if ($this->package_duration_value !== 1) {
            $unit = str($unit)->plural()->toString();
        }

        return "{$this->package_duration_value} {$unit}";
    }
}

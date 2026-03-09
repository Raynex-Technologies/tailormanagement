<?php

namespace App\Models;

use App\Enums\InstallmentScheduleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'installment_plan_id',
        'installment_number',
        'due_date',
        'scheduled_amount',
        'paid_amount',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'due_date' => 'date',
            'scheduled_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => InstallmentScheduleStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function installmentPlan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class);
    }

    public function getOutstandingAmountAttribute(): float
    {
        return max(0, (float) $this->scheduled_amount - (float) $this->paid_amount);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [InstallmentScheduleStatus::Pending, InstallmentScheduleStatus::Partial], true)
            && $this->due_date?->isPast();
    }
}

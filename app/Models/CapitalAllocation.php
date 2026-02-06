<?php

namespace App\Models;

use App\Enums\CapitalAllocationStatus;
use App\Models\Concerns\BranchScoped;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapitalAllocation extends Model
{
    use BranchScoped, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (CapitalAllocation $allocation) {
            if (empty($allocation->allocation_no)) {
                $allocation->allocation_no = DocNumber::capitalAllocation();
            }
        });
    }

    protected $fillable = [
        'branch_id',
        'allocation_no',
        'accountant_id',
        'starts_on',
        'ends_on',
        'initial_amount',
        'spent_amount',
        'closing_balance',
        'status',
        'closed_at',
        'carried_over_to_id',
        'created_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => CapitalAllocationStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
            'initial_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function accountant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function carriedOverTo(): BelongsTo
    {
        return $this->belongsTo(CapitalAllocation::class, 'carried_over_to_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CapitalTransaction::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get remaining balance.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return $this->initial_amount - $this->spent_amount;
    }
}

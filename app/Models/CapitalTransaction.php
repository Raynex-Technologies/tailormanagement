<?php

namespace App\Models;

use App\Enums\CapitalTransactionType;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CapitalTransaction extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'capital_allocation_id',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'created_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => CapitalTransactionType::class,
            'amount' => 'decimal:2',
        ];
    }

    public function capitalAllocation(): BelongsTo
    {
        return $this->belongsTo(CapitalAllocation::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

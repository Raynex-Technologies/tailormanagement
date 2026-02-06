<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use App\Models\Concerns\BranchScoped;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseRequest extends Model
{
    use BranchScoped, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PurchaseRequest $request) {
            if (empty($request->request_no)) {
                $request->request_no = DocNumber::purchaseRequest();
            }
        });
    }

    protected $fillable = [
        'branch_id',
        'request_no',
        'requested_by',
        'reviewed_by',
        'status',
        'estimated_total',
        'note',
        'capital_allocation_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'estimated_total' => 'decimal:2',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function capitalAllocation(): BelongsTo
    {
        return $this->belongsTo(CapitalAllocation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }
}

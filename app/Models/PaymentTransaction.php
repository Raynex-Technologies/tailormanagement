<?php

namespace App\Models;

use App\Enums\PaymentTransactionPurpose;
use App\Enums\PaymentTransactionStatus;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'order_id',
        'customer_id',
        'payment_method_id',
        'gateway',
        'merchant_reference',
        'gateway_reference',
        'amount',
        'currency',
        'status',
        'purpose',
        'checkout_url',
        'initiated_at',
        'verified_at',
        'failed_at',
        'failure_reason',
        'request_payload',
        'response_payload',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentTransactionStatus::class,
            'purpose' => PaymentTransactionPurpose::class,
            'initiated_at' => 'datetime',
            'verified_at' => 'datetime',
            'failed_at' => 'datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerEmailDelivery extends Model
{
    use HasFactory;

    public const TYPE_ORDER_CREATED = 'order_created';

    public const TYPE_PAYMENT_RECEIVED = 'payment_received';

    public const TYPE_INVOICE_MANUAL = 'invoice_manual';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'branch_id',
        'order_id',
        'invoice_id',
        'order_payment_id',
        'type',
        'delivery_key',
        'recipient',
        'status',
        'attempts',
        'sent_at',
        'failed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }
}

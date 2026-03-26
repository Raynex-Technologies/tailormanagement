<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomOrderProgressUpdate extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'stage_key',
        'stage_label',
        'note',
        'is_customer_visible',
        'requested_payment_amount',
        'requested_payment_note',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_customer_visible' => 'boolean',
            'requested_payment_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

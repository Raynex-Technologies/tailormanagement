<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNote extends Model
{
    use BranchScoped, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (DeliveryNote $note) {
            if (empty($note->delivery_note_no)) {
                $note->delivery_note_no = DocNumber::deliveryNote();
            }
        });
    }

    protected $fillable = [
        'branch_id',
        'delivery_note_no',
        'order_id',
        'delivered_at',
        'delivered_by',
        'received_by_name',
        'received_by_phone',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * Alias for deliverer relationship.
     */
    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }
}

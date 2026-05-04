<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    public const BLOCKING_STATUSES = [
        'requested',
        'pending_approval',
        'confirmed',
        'reschedule_requested',
    ];

    protected $fillable = [
        'appointment_number',
        'online_booking_id',
        'customer_id',
        'branch_id',
        'appointment_type_id',
        'scheduled_start_at',
        'scheduled_end_at',
        'status',
        'customer_name',
        'customer_phone',
        'customer_email',
        'location_note',
        'internal_note',
        'customer_note',
        'requested_by_customer',
        'approved_by',
        'approved_at',
        'declined_by',
        'declined_at',
        'decline_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'requested_by_customer' => 'boolean',
            'approved_at' => 'datetime',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(OnlineBooking::class, 'online_booking_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class);
    }
}

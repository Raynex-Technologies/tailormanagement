<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnlineBooking extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'new_custom_order',
        'repeat_previous_order',
        'alteration_repair',
        'measurement_appointment',
        'fitting_appointment',
        'style_consultation',
        'bulk_uniform_order',
    ];

    public const STATUSES = [
        'draft',
        'submitted',
        'pending_review',
        'awaiting_customer_response',
        'awaiting_deposit',
        'confirmed',
        'declined',
        'cancelled',
        'converted_to_order',
    ];

    protected $fillable = [
        'booking_number',
        'customer_id',
        'branch_id',
        'booking_type',
        'status',
        'customer_name',
        'customer_phone',
        'customer_whatsapp',
        'customer_email',
        'customer_location',
        'preferred_contact_method',
        'preferred_language',
        'notes',
        'needed_by_date',
        'event_date',
        'is_urgent',
        'source',
        'reviewed_by',
        'reviewed_at',
        'decline_reason',
        'converted_order_id',
        'measurement_option',
        'measurement_profile_id',
        'payload',
        'internal_note',
    ];

    protected function casts(): array
    {
        return [
            'needed_by_date' => 'date',
            'event_date' => 'date',
            'is_urgent' => 'boolean',
            'reviewed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OnlineBookingItem::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(OnlineBookingImage::class);
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function measurementProfile(): BelongsTo
    {
        return $this->belongsTo(MeasurementProfile::class);
    }
}

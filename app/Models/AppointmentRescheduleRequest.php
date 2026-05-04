<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentRescheduleRequest extends Model
{
    protected $fillable = [
        'appointment_id',
        'requested_start_at',
        'requested_end_at',
        'reason',
        'status',
        'requested_by_type',
        'requested_by_id',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_start_at' => 'datetime',
            'requested_end_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}

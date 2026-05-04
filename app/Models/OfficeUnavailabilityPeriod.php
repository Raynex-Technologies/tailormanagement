<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeUnavailabilityPeriod extends Model
{
    protected $fillable = [
        'branch_id',
        'appointment_type_id',
        'title',
        'reason',
        'starts_at',
        'ends_at',
        'is_full_day',
        'repeats_yearly',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_full_day' => 'boolean',
            'repeats_yearly' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }
}

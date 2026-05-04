<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementValue extends Model
{
    protected $fillable = [
        'measurement_profile_id',
        'measurement_field_id',
        'value',
        'unit',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(MeasurementProfile::class, 'measurement_profile_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(MeasurementField::class, 'measurement_field_id');
    }
}

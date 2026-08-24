<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MeasurementValue extends Model
{
    protected $fillable = [
        'measurement_profile_id',
        'measurement_field_id',
        'field_code_snapshot',
        'field_label_snapshot',
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

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Saved measurement values are immutable.');
        });
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

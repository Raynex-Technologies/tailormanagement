<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GarmentCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'gender_scope',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(GarmentOptionGroup::class);
    }

    public function legacyMeasurementFields(): HasMany
    {
        return $this->hasMany(MeasurementField::class);
    }

    public function measurementFields(): BelongsToMany
    {
        return $this->belongsToMany(MeasurementField::class)
            ->withPivot(['is_required', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('measurement_fields.name');
    }
}

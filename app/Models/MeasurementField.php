<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MeasurementField extends Model
{
    protected $fillable = [
        'garment_category_id',
        'name',
        'slug',
        'code',
        'unit',
        'default_unit',
        'instructions',
        'is_global',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_global' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MeasurementField $field): void {
            if ($field->isDirty('default_unit')) {
                $field->unit = $field->default_unit;
            } elseif ($field->isDirty('unit') && blank($field->default_unit)) {
                $field->default_unit = $field->unit;
            }
        });
    }

    public function garmentCategory(): BelongsTo
    {
        return $this->belongsTo(GarmentCategory::class);
    }

    public function garmentCategories(): BelongsToMany
    {
        return $this->belongsToMany(GarmentCategory::class)
            ->withPivot(['is_required', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('garment_categories.name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeGloballyApplicable(Builder $query): Builder
    {
        return $query->where('is_global', true);
    }
}

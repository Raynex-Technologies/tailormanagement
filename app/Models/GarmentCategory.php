<?php

namespace App\Models;
use App\Services\Media\ImageUploadService;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GarmentCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'image_alt',
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

    public function fabrics(): BelongsToMany
    {
        return $this->belongsToMany(Fabric::class)->withTimestamps();
    }

    public function getImageUrlAttribute(): ?string
    {
        return app(ImageUploadService::class)->publicUrl($this->image_path);
    }

    public function setImagePathAttribute(mixed $value): void
    {
        $this->attributes['image_path'] = app(ImageUploadService::class)->normalizePublicPath($value);
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

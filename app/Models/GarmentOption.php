<?php

namespace App\Models;
use App\Services\Media\ImageUploadService;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GarmentOption extends Model
{
    protected $fillable = [
        'garment_option_group_id',
        'label',
        'value',
        'description',
        'image_path',
        'image_alt',
        'price_adjustment',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GarmentOptionGroup::class, 'garment_option_group_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return app(ImageUploadService::class)->publicUrl($this->image_path);
    }

    public function setImagePathAttribute(mixed $value): void
    {
        $this->attributes['image_path'] = app(ImageUploadService::class)->normalizePublicPath($value);
    }
}

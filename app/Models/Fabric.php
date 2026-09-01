<?php

namespace App\Models;

use App\Services\Media\ImageUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fabric extends Model
{
    protected $fillable = ['name', 'code', 'description', 'composition', 'care_information', 'image_path', 'image_alt', 'inventory_item_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function garmentCategories(): BelongsToMany
    {
        return $this->belongsToMany(GarmentCategory::class)->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(FabricVariant::class)->orderBy('sort_order')->orderBy('name');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
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

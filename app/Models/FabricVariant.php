<?php

namespace App\Models;

use App\Services\Media\ImageUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabricVariant extends Model
{
    protected $fillable = ['fabric_id', 'name', 'color_name', 'swatch_hex', 'image_path', 'image_alt', 'inventory_item_variant_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    public function inventoryVariant(): BelongsTo
    {
        return $this->belongsTo(InventoryItemVariant::class, 'inventory_item_variant_id');
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

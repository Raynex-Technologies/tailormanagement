<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use App\Support\StorefrontMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorefrontProductCombo extends Model
{
    use BranchScoped, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'slug',
        'description',
        'featured_image_path',
        'price',
        'is_active',
        'storefront_is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'storefront_is_visible' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StorefrontProductComboItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            InventoryItem::class,
            'storefront_product_combo_items',
            'storefront_product_combo_id',
            'inventory_item_id'
        )->withPivot('quantity')->withTimestamps();
    }

    public function storefrontRouteKey(): string
    {
        $slug = trim((string) $this->slug);

        return $slug !== '' ? $slug : (string) $this->getKey();
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        return StorefrontMedia::url($this->featured_image_path);
    }

    public function setFeaturedImagePathAttribute(mixed $value): void
    {
        $this->attributes['featured_image_path'] = StorefrontMedia::normalizePath($value);
    }
}

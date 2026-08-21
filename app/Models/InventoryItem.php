<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use App\Support\StorefrontMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryItem extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'inventory_category_id',
        'inventory_unit_id',
        'sku',
        'name',
        'slug',
        'unit',
        'short_description',
        'full_description',
        'featured_image_path',
        'gallery_images',
        'status',
        'reorder_level',
        'low_stock_threshold',
        'default_buy_price',
        'default_sell_price',
        'compare_at_price',
        'track_stock',
        'allow_backorders',
        'storefront_is_visible',
        'is_featured',
        'weight',
        'dimensions',
        'shipping_profile_id',
        'is_taxable',
        'meta_title',
        'meta_description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:2',
            'low_stock_threshold' => 'decimal:2',
            'default_buy_price' => 'decimal:2',
            'default_sell_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'track_stock' => 'boolean',
            'allow_backorders' => 'boolean',
            'storefront_is_visible' => 'boolean',
            'is_featured' => 'boolean',
            'weight' => 'decimal:3',
            'dimensions' => 'array',
            'gallery_images' => 'array',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    public function stock(): HasOne
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function posSaleItems(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(InventoryItemMedia::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(InventoryItemVariant::class);
    }

    public function shippingProfile(): BelongsTo
    {
        return $this->belongsTo(ShippingProfile::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'inventory_item_product_tag');
    }

    public function purchaseRequestItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function stockRequestItems(): HasMany
    {
        return $this->hasMany(OrderStockRequestItem::class);
    }

    public function orderPackageComponents(): HasMany
    {
        return $this->hasMany(OrderPackageTemplateItem::class);
    }

    public function scopeStorefrontVisible($query)
    {
        return $query
            ->where('storefront_is_visible', true)
            ->where('status', 'active')
            ->where('is_active', true);
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

    public function getGalleryImageUrlsAttribute(): array
    {
        return collect((array) $this->gallery_images)
            ->map(fn ($path) => StorefrontMedia::url($path))
            ->filter()
            ->values()
            ->all();
    }

    public function setFeaturedImagePathAttribute(mixed $value): void
    {
        $this->attributes['featured_image_path'] = StorefrontMedia::normalizePath($value);
    }

    public function setGalleryImagesAttribute(mixed $value): void
    {
        $paths = collect(is_array($value) ? $value : [])
            ->map(fn ($path) => StorefrontMedia::normalizePath($path))
            ->filter()
            ->values()
            ->all();

        $this->attributes['gallery_images'] = json_encode($paths, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get current available quantity (on_hand - reserved).
     */
    public function getAvailableQtyAttribute(): float
    {
        return ($this->stock?->qty_on_hand ?? 0) - ($this->stock?->qty_reserved ?? 0);
    }

    /**
     * Check if stock is below reorder level.
     */
    public function isLowStock(): bool
    {
        return ($this->stock?->qty_on_hand ?? 0) <= $this->reorder_level;
    }
}

<?php

namespace App\Models;

use App\Enums\OrderCatalogItemType;
use App\Enums\OrderCatalogQuantityBehavior;
use App\Services\Media\ImageUploadService;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderCatalogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'garment_category_id',
        'default_selling_price',
        'image_path',
        'requires_measurements',
        'quantity_behavior',
        'available_all_branches',
        'archived_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderCatalogItem $item): void {
            if (blank($item->code)) {
                $item->code = DocNumber::orderCatalogItem();
            }

            if (blank($item->quantity_behavior)) {
                $type = $item->type instanceof OrderCatalogItemType
                    ? $item->type
                    : OrderCatalogItemType::from((string) $item->type);
                $item->quantity_behavior = OrderCatalogQuantityBehavior::defaultFor($type);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => OrderCatalogItemType::class,
            'quantity_behavior' => OrderCatalogQuantityBehavior::class,
            'default_selling_price' => 'decimal:2',
            'requires_measurements' => 'boolean',
            'available_all_branches' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'order_catalog_item_branch')->withTimestamps();
    }

    public function garmentCategory(): BelongsTo
    {
        return $this->belongsTo(GarmentCategory::class);
    }

    public function packageComponents(): HasMany
    {
        return $this->hasMany(OrderPackageTemplateItem::class);
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeAvailableForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where(function (Builder $availability) use ($branchId): void {
            $availability->where('available_all_branches', true)
                ->orWhereHas('branches', fn (Builder $branches) => $branches->whereKey($branchId));
        });
    }

    public function isAvailableForBranch(int $branchId): bool
    {
        return $this->available_all_branches || $this->branches()->whereKey($branchId)->exists();
    }

    public function archive(): bool
    {
        return $this->update(['archived_at' => now()]);
    }

    public function restoreFromArchive(): bool
    {
        return $this->update(['archived_at' => null]);
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

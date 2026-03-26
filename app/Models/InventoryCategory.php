<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InventoryCategory extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'storefront_is_visible',
        'storefront_featured',
        'storefront_image_path',
        'seo_title',
        'seo_description',
    ];

    protected static function booted(): void
    {
        static::creating(function (InventoryCategory $category) {
            $category->slug = static::resolveSlug($category->slug, $category->name, $category->branch_id);
        });

        static::updating(function (InventoryCategory $category) {
            if (! $category->isDirty('slug') && filled($category->slug)) {
                return;
            }

            $category->slug = static::resolveSlug(
                $category->slug,
                $category->name,
                $category->branch_id,
                $category->getKey()
            );
        });
    }

    protected function casts(): array
    {
        return [
            'storefront_is_visible' => 'boolean',
            'storefront_featured' => 'boolean',
        ];
    }

    protected static function resolveSlug(?string $slug, string $name, ?int $branchId, ?int $ignoreId = null): string
    {
        $normalizedSlug = Str::slug((string) $slug);

        if ($normalizedSlug !== '') {
            return $normalizedSlug;
        }

        return static::generateUniqueSlug($name, $branchId, $ignoreId);
    }

    protected static function generateUniqueSlug(string $name, ?int $branchId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'category';
        }

        $candidate = $baseSlug;
        $suffix = 2;

        while (static::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}

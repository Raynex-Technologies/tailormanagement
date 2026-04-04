<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InventoryCategory extends Model
{
    use BranchScoped, HasFactory;

    public const STOREFRONT_IMAGE_DISK = 'storefront_categories';

    public const LEGACY_STOREFRONT_IMAGE_PREFIX = 'storefront/categories/';

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

        static::deleting(function (InventoryCategory $category) {
            $path = static::normalizeStorefrontImagePath($category->storefront_image_path);

            if (blank($path)) {
                return;
            }

            $isShared = static::withoutBranchScope()
                ->whereKeyNot($category->getKey())
                ->where('storefront_image_path', $path)
                ->exists();

            if ($isShared) {
                return;
            }

            static::deleteStorefrontImageFile($path);
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

    public function getImageUrlAttribute(): ?string
    {
        $path = static::normalizeStorefrontImagePath($this->storefront_image_path);

        if (blank($path)) {
            return null;
        }

        return Storage::disk(static::STOREFRONT_IMAGE_DISK)->url($path);
    }

    public function setStorefrontImagePathAttribute(mixed $value): void
    {
        $this->attributes['storefront_image_path'] = static::normalizeStorefrontImagePath($value);
    }

    public static function normalizeStorefrontImagePath(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $path = trim((string) $value);

        if ($path === '') {
            return null;
        }

        $parsedPath = parse_url($path, PHP_URL_PATH);
        if (is_string($parsedPath) && $parsedPath !== '') {
            $path = $parsedPath;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = ltrim($path, '/');

        $prefixes = [
            'uploads/categories/',
            'storage/uploads/categories/',
            'storage/'.static::LEGACY_STOREFRONT_IMAGE_PREFIX,
            static::LEGACY_STOREFRONT_IMAGE_PREFIX,
        ];

        foreach ($prefixes as $prefix) {
            if (! Str::startsWith($path, $prefix)) {
                continue;
            }

            $path = substr($path, strlen($prefix));
            break;
        }

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..'
        ));

        $normalizedPath = implode('/', $segments);

        return $normalizedPath === '' ? null : $normalizedPath;
    }

    public static function ensureStorefrontImageDirectoryExists(): void
    {
        File::ensureDirectoryExists(Storage::disk(static::STOREFRONT_IMAGE_DISK)->path(''));
    }

    public static function deleteStorefrontImageFile(mixed $value): void
    {
        $path = static::normalizeStorefrontImagePath($value);

        if (blank($path)) {
            return;
        }

        Storage::disk(static::STOREFRONT_IMAGE_DISK)->delete($path);
        Storage::disk('public')->delete(static::LEGACY_STOREFRONT_IMAGE_PREFIX.$path);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InventoryCategory extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'slug',
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
}

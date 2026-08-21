<?php

namespace App\Models;

use App\Services\Media\ImageUploadService;
use App\Services\Orders\OrderPackagePricingService;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPackageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'cover_image_path',
        'revision',
        'available_all_branches',
        'archived_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderPackageTemplate $template): void {
            if (blank($template->code)) {
                $template->code = DocNumber::orderPackageTemplate();
            }

            if (! $template->revision) {
                $template->revision = 1;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'available_all_branches' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderPackageTemplateItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'order_package_template_branch')->withTimestamps();
    }

    public function instances(): HasMany
    {
        return $this->hasMany(OrderPackageInstance::class);
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

    /**
     * Explicitly mark a commercially meaningful template revision.
     */
    public function bumpRevision(): int
    {
        $this->increment('revision');
        $this->refresh();

        return $this->revision;
    }

    public function defaultTotal(): string
    {
        return app(OrderPackagePricingService::class)->defaultTotal($this);
    }

    public function standardValue(): string
    {
        return app(OrderPackagePricingService::class)->standardValue($this);
    }

    public function savings(): string
    {
        return app(OrderPackagePricingService::class)->savings($this);
    }

    public function configuredTotal(array $configuredQuantities): string
    {
        return app(OrderPackagePricingService::class)->configuredTotal($this, $configuredQuantities);
    }

    public function snapshot(array $configuredQuantities = []): array
    {
        return app(OrderPackagePricingService::class)->snapshot($this, $configuredQuantities);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return app(ImageUploadService::class)->publicUrl($this->cover_image_path);
    }

    public function setCoverImagePathAttribute(mixed $value): void
    {
        $this->attributes['cover_image_path'] = app(ImageUploadService::class)->normalizePublicPath($value);
    }
}

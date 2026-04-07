<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemMedia;
use App\Models\PackageItem;
use App\Models\StorefrontProductCombo;
use App\Services\Media\ImageUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class NormalizeImagePaths extends Command
{
    protected $signature = 'media:normalize-image-paths
        {--dry-run : Preview normalization and migration without changing the database}';

    protected $description = 'Normalize stored image paths to relative public_uploads paths and migrate legacy files where available.';

    public function __construct(
        protected ImageUploadService $imageUploadService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stats = [
            'scanned' => 0,
            'normalized' => 0,
            'copied' => 0,
            'missing' => 0,
        ];

        $this->components->info('Scanning image path columns...');
        if ($dryRun) {
            $this->components->warn('Dry run mode enabled. No database rows will be updated.');
        }

        $this->normalizeBusinessSettings($dryRun, $stats);
        $this->normalizeCategories($dryRun, $stats);
        $this->normalizeItems($dryRun, $stats);
        $this->normalizeItemMedia($dryRun, $stats);
        $this->normalizePackageItems($dryRun, $stats);
        $this->normalizeCombos($dryRun, $stats);

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Scanned values', $stats['scanned']],
                ['Normalized values', $stats['normalized']],
                ['Legacy files copied', $stats['copied']],
                ['Missing source files', $stats['missing']],
            ]
        );

        return self::SUCCESS;
    }

    protected function normalizeBusinessSettings(bool $dryRun, array &$stats): void
    {
        $columns = [
            'logo_path',
            'storefront_logo_path',
            'storefront_favicon_path',
            'storefront_hero_media_path',
            'storefront_social_image_path',
        ];

        BusinessSetting::query()
            ->orderBy('id')
            ->chunkById(50, function ($rows) use ($columns, $dryRun, &$stats): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $stats['scanned']++;
                        $original = $row->getRawOriginal($column);
                        $normalized = $this->imageUploadService->normalizePublicPath($original);

                        if ($normalized !== null) {
                            $this->attemptLegacyCopy($normalized, $stats);
                        }

                        if ($normalized === $original) {
                            continue;
                        }

                        $stats['normalized']++;
                        $updates[$column] = $normalized;
                    }

                    if ($updates !== [] && ! $dryRun) {
                        $row->forceFill($updates)->save();
                    }
                }
            });
    }

    protected function normalizeCategories(bool $dryRun, array &$stats): void
    {
        InventoryCategory::withoutBranchScope()
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($dryRun, &$stats): void {
                foreach ($rows as $row) {
                    $stats['scanned']++;

                    $original = $row->getRawOriginal('storefront_image_path');
                    $normalized = InventoryCategory::normalizeStorefrontImagePath($original);

                    if ($normalized !== null) {
                        $this->attemptLegacyCopy($normalized, $stats);
                    }

                    if ($normalized === $original) {
                        continue;
                    }

                    $stats['normalized']++;

                    if ($dryRun) {
                        continue;
                    }

                    $row->storefront_image_path = $normalized;
                    $row->save();
                }
            });
    }

    protected function normalizeItems(bool $dryRun, array &$stats): void
    {
        InventoryItem::withoutBranchScope()
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($dryRun, &$stats): void {
                foreach ($rows as $row) {
                    $updates = [];

                    $stats['scanned']++;
                    $originalFeatured = $row->getRawOriginal('featured_image_path');
                    $normalizedFeatured = $this->imageUploadService->normalizePublicPath($originalFeatured);

                    if ($normalizedFeatured !== null) {
                        $this->attemptLegacyCopy($normalizedFeatured, $stats);
                    }

                    if ($normalizedFeatured !== $originalFeatured) {
                        $stats['normalized']++;
                        $updates['featured_image_path'] = $normalizedFeatured;
                    }

                    $stats['scanned']++;
                    $originalGallery = $row->getRawOriginal('gallery_images');
                    $decoded = json_decode((string) $originalGallery, true);
                    $gallery = is_array($decoded) ? $decoded : [];

                    $normalizedGallery = collect($gallery)
                        ->map(fn ($path) => $this->imageUploadService->normalizePublicPath($path))
                        ->filter()
                        ->values()
                        ->all();

                    foreach ($normalizedGallery as $path) {
                        $this->attemptLegacyCopy($path, $stats);
                    }

                    $normalizedGalleryJson = json_encode($normalizedGallery, JSON_UNESCAPED_SLASHES);
                    if ($normalizedGalleryJson !== $originalGallery) {
                        $stats['normalized']++;
                        $updates['gallery_images'] = $normalizedGalleryJson;
                    }

                    if ($updates !== [] && ! $dryRun) {
                        $row->forceFill($updates)->save();
                    }
                }
            });
    }

    protected function normalizeItemMedia(bool $dryRun, array &$stats): void
    {
        InventoryItemMedia::query()
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dryRun, &$stats): void {
                foreach ($rows as $row) {
                    $stats['scanned']++;

                    $original = $row->getRawOriginal('path');
                    $normalized = $this->imageUploadService->normalizePublicPath($original);

                    if ($normalized !== null) {
                        $this->attemptLegacyCopy($normalized, $stats);
                    }

                    if ($normalized === $original) {
                        continue;
                    }

                    $stats['normalized']++;

                    if ($dryRun) {
                        continue;
                    }

                    $row->path = $normalized;
                    $row->save();
                }
            });
    }

    protected function normalizePackageItems(bool $dryRun, array &$stats): void
    {
        PackageItem::query()
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dryRun, &$stats): void {
                foreach ($rows as $row) {
                    $stats['scanned']++;

                    $original = $row->getRawOriginal('image_path');
                    $normalized = $this->imageUploadService->normalizePublicPath($original);

                    if ($normalized !== null) {
                        $this->attemptLegacyCopy($normalized, $stats);
                    }

                    if ($normalized === $original) {
                        continue;
                    }

                    $stats['normalized']++;

                    if ($dryRun) {
                        continue;
                    }

                    $row->image_path = $normalized;
                    $row->save();
                }
            });
    }

    protected function normalizeCombos(bool $dryRun, array &$stats): void
    {
        StorefrontProductCombo::withoutBranchScope()
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dryRun, &$stats): void {
                foreach ($rows as $row) {
                    $stats['scanned']++;

                    $original = $row->getRawOriginal('featured_image_path');
                    $normalized = $this->imageUploadService->normalizePublicPath($original);

                    if ($normalized !== null) {
                        $this->attemptLegacyCopy($normalized, $stats);
                    }

                    if ($normalized === $original) {
                        continue;
                    }

                    $stats['normalized']++;

                    if ($dryRun) {
                        continue;
                    }

                    $row->featured_image_path = $normalized;
                    $row->save();
                }
            });
    }

    protected function attemptLegacyCopy(string $path, array &$stats): void
    {
        $disk = Storage::disk(ImageUploadService::PUBLIC_DISK);
        $existsBefore = $disk->exists($path);
        $resolvedPath = $this->imageUploadService->publicPath($path);
        $existsAfter = $resolvedPath !== null && $disk->exists($path);

        if (! $existsBefore && $existsAfter) {
            $stats['copied']++;

            return;
        }

        if (! $existsAfter) {
            $stats['missing']++;
        }
    }
}

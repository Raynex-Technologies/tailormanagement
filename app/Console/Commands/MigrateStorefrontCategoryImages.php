<?php

namespace App\Console\Commands;

use App\Models\InventoryCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateStorefrontCategoryImages extends Command
{
    protected $signature = 'storefront:migrate-category-images
        {--move : Move legacy files after successful migration (default behavior is copy)}
        {--dry-run : Preview actions without writing files or database updates}';

    protected $description = 'Normalize storefront category image paths and migrate legacy files into public/uploads/categories.';

    public function handle(): int
    {
        $targetDisk = InventoryCategory::STOREFRONT_IMAGE_DISK;
        $legacyDisk = 'public';
        $move = (bool) $this->option('move');
        $dryRun = (bool) $this->option('dry-run');

        InventoryCategory::ensureStorefrontImageDirectoryExists();

        $stats = [
            'scanned' => 0,
            'normalized' => 0,
            'files_migrated' => 0,
            'skipped_existing' => 0,
            'skipped_invalid' => 0,
            'missing_files' => 0,
            'copy_failed' => 0,
        ];

        $this->info('Scanning category image paths...');
        if ($dryRun) {
            $this->comment('Dry run enabled. No files or database rows will be changed.');
        } elseif ($move) {
            $this->comment('Move mode enabled. Legacy files will be deleted after successful copy.');
        }

        InventoryCategory::withoutBranchScope()
            ->whereNotNull('storefront_image_path')
            ->where('storefront_image_path', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($categories) use (&$stats, $targetDisk, $legacyDisk, $move, $dryRun): void {
                foreach ($categories as $category) {
                    $this->migrateCategory(
                        category: $category,
                        targetDisk: $targetDisk,
                        legacyDisk: $legacyDisk,
                        move: $move,
                        dryRun: $dryRun,
                        stats: $stats
                    );
                }
            });

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Scanned categories', $stats['scanned']],
                ['Normalized DB values', $stats['normalized']],
                ['Migrated files', $stats['files_migrated']],
                ['Skipped (already exists)', $stats['skipped_existing']],
                ['Skipped (invalid/empty path)', $stats['skipped_invalid']],
                ['Missing legacy files', $stats['missing_files']],
                ['Copy failures', $stats['copy_failed']],
            ]
        );

        return $stats['copy_failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function migrateCategory(
        InventoryCategory $category,
        string $targetDisk,
        string $legacyDisk,
        bool $move,
        bool $dryRun,
        array &$stats
    ): void {
        $stats['scanned']++;

        $originalValue = (string) ($category->getRawOriginal('storefront_image_path') ?? '');
        $normalizedPath = InventoryCategory::normalizeStorefrontImagePath($originalValue);

        if (blank($normalizedPath)) {
            $stats['skipped_invalid']++;
            $this->warn("Category #{$category->id}: skipped invalid image path '{$originalValue}'.");

            return;
        }

        if (Storage::disk($targetDisk)->exists($normalizedPath)) {
            $stats['skipped_existing']++;
        } else {
            $legacySourcePath = $this->resolveLegacySourcePath($originalValue, $normalizedPath, $legacyDisk);

            if ($legacySourcePath === null) {
                $stats['missing_files']++;
                $this->warn("Category #{$category->id}: legacy file not found for '{$originalValue}'.");
            } elseif ($dryRun) {
                $stats['files_migrated']++;
                $action = $move ? 'would move' : 'would copy';
                $this->line("Category #{$category->id}: {$action} '{$legacySourcePath}' => '{$normalizedPath}'.");
            } else {
                try {
                    $migrated = $this->copyFromLegacyDisk(
                        sourcePath: $legacySourcePath,
                        destinationPath: $normalizedPath,
                        legacyDisk: $legacyDisk,
                        targetDisk: $targetDisk,
                        move: $move
                    );
                } catch (Throwable $exception) {
                    $migrated = false;
                    $this->error("Category #{$category->id}: failed to migrate '{$legacySourcePath}' ({$exception->getMessage()}).");
                }

                if ($migrated) {
                    $stats['files_migrated']++;
                } else {
                    $stats['copy_failed']++;
                    $this->error("Category #{$category->id}: unable to migrate '{$legacySourcePath}'.");
                }
            }
        }

        if ($originalValue === $normalizedPath) {
            return;
        }

        $stats['normalized']++;

        if ($dryRun) {
            $this->line("Category #{$category->id}: would normalize '{$originalValue}' => '{$normalizedPath}'.");

            return;
        }

        $category->storefront_image_path = $normalizedPath;
        $category->save();
    }

    protected function resolveLegacySourcePath(string $originalValue, string $normalizedPath, string $legacyDisk): ?string
    {
        foreach ($this->legacySourceCandidates($originalValue, $normalizedPath) as $candidate) {
            if (Storage::disk($legacyDisk)->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function legacySourceCandidates(string $originalValue, string $normalizedPath): array
    {
        $candidates = [
            $originalValue,
            parse_url($originalValue, PHP_URL_PATH),
            InventoryCategory::LEGACY_STOREFRONT_IMAGE_PREFIX.$normalizedPath,
        ];

        return collect($candidates)
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $path): string => str_replace('\\', '/', trim($path)))
            ->map(fn (string $path): string => ltrim($path, '/'))
            ->map(function (string $path): string {
                if (str_starts_with($path, 'storage/')) {
                    return substr($path, strlen('storage/'));
                }

                return $path;
            })
            ->filter(fn (string $path): bool => $path !== '' && ! str_starts_with($path, 'uploads/categories/'))
            ->unique()
            ->values()
            ->all();
    }

    protected function copyFromLegacyDisk(
        string $sourcePath,
        string $destinationPath,
        string $legacyDisk,
        string $targetDisk,
        bool $move
    ): bool {
        $sourceFilesystem = Storage::disk($legacyDisk);
        $targetFilesystem = Storage::disk($targetDisk);

        $stream = $sourceFilesystem->readStream($sourcePath);
        if ($stream === false) {
            return false;
        }

        try {
            $copied = $targetFilesystem->put($destinationPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $copied) {
            return false;
        }

        if ($move) {
            $sourceFilesystem->delete($sourcePath);
        }

        return true;
    }
}


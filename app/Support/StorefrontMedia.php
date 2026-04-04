<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class StorefrontMedia
{
    public const DISK = 'storefront_uploads';

    public const LEGACY_DISK = 'public';

    public static function normalizePath(mixed $value): ?string
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

        foreach (['uploads/', 'storage/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..'
        ));

        $normalizedPath = implode('/', $segments);

        return $normalizedPath === '' ? null : $normalizedPath;
    }

    public static function url(mixed $value): ?string
    {
        $path = static::normalizePath($value);
        if ($path === null) {
            return null;
        }

        static::migrateLegacyFileIfNeeded($path);

        return Storage::disk(static::DISK)->url($path);
    }

    public static function ensureDirectoryExists(string $relativeDirectory = ''): void
    {
        File::ensureDirectoryExists(Storage::disk(static::DISK)->path($relativeDirectory));
    }

    public static function store(mixed $upload, string $directory): string
    {
        static::ensureDirectoryExists($directory);

        /** @var string $storedPath */
        $storedPath = $upload->store($directory, static::DISK);

        return static::normalizePath($storedPath) ?? $storedPath;
    }

    public static function delete(mixed $value): void
    {
        $path = static::normalizePath($value);
        if ($path === null) {
            return;
        }

        Storage::disk(static::DISK)->delete($path);
        Storage::disk(static::LEGACY_DISK)->delete($path);
    }

    protected static function migrateLegacyFileIfNeeded(string $normalizedPath): void
    {
        $targetDisk = Storage::disk(static::DISK);
        if ($targetDisk->exists($normalizedPath)) {
            return;
        }

        $legacyDisk = Storage::disk(static::LEGACY_DISK);
        if (! $legacyDisk->exists($normalizedPath)) {
            return;
        }

        $directory = dirname($normalizedPath);
        if ($directory !== '.' && $directory !== DIRECTORY_SEPARATOR) {
            static::ensureDirectoryExists($directory);
        }

        $stream = $legacyDisk->readStream($normalizedPath);
        if ($stream === false) {
            return;
        }

        try {
            $targetDisk->put($normalizedPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}

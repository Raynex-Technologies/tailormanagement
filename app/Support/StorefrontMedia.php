<?php

namespace App\Support;

use App\Services\Media\ImageUploadService;

class StorefrontMedia
{
    public const DISK = ImageUploadService::PUBLIC_DISK;

    public const LEGACY_DISK = 'public';

    public static function normalizePath(mixed $value): ?string
    {
        return app(ImageUploadService::class)->normalizePublicPath($value);
    }

    public static function url(mixed $value): ?string
    {
        return app(ImageUploadService::class)->publicUrl($value);
    }

    public static function ensureDirectoryExists(string $relativeDirectory = ''): void
    {
        app(ImageUploadService::class)->ensurePublicDirectory($relativeDirectory);
    }

    public static function store(mixed $upload, string $directory): string
    {
        return app(ImageUploadService::class)->storePublic($upload, $directory)->path;
    }

    public static function delete(mixed $value): void
    {
        app(ImageUploadService::class)->deletePublic($value);
    }
}

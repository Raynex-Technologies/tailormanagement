<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageUploadService
{
    public const PUBLIC_DISK = 'public_uploads';

    public const PRIVATE_DISK = 'private_uploads';

    /**
     * @var array<string, string>
     */
    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function storePublic(mixed $upload, string $directory): ImageUploadResult
    {
        $path = $this->store($upload, self::PUBLIC_DISK, $directory, true);

        return new ImageUploadResult(
            disk: self::PUBLIC_DISK,
            path: $path,
            isPrivate: false,
            url: $this->publicUrl($path)
        );
    }

    public function storePrivate(mixed $upload, string $directory): ImageUploadResult
    {
        $path = $this->store($upload, self::PRIVATE_DISK, $directory, false);

        return new ImageUploadResult(
            disk: self::PRIVATE_DISK,
            path: $path,
            isPrivate: true,
            url: null
        );
    }

    public function replacePublic(mixed $upload, mixed $existingPath, string $directory): ImageUploadResult
    {
        $stored = $this->storePublic($upload, $directory);

        $normalizedCurrent = $this->normalizePublicPath($existingPath);
        if ($normalizedCurrent !== null && $normalizedCurrent !== $stored->path) {
            $this->deletePublic($normalizedCurrent);
        }

        return $stored;
    }

    public function replacePrivate(mixed $upload, mixed $existingPath, string $directory): ImageUploadResult
    {
        $stored = $this->storePrivate($upload, $directory);

        $normalizedCurrent = $this->normalizePrivatePath($existingPath);
        if ($normalizedCurrent !== null && $normalizedCurrent !== $stored->path) {
            $this->deletePrivate($normalizedCurrent);
        }

        return $stored;
    }

    public function deletePublic(mixed $value): void
    {
        $path = $this->normalizePublicPath($value);
        if ($path === null) {
            return;
        }

        Storage::disk(self::PUBLIC_DISK)->delete($path);
    }

    public function deletePrivate(mixed $value): void
    {
        $path = $this->normalizePrivatePath($value);
        if ($path === null) {
            return;
        }

        Storage::disk(self::PRIVATE_DISK)->delete($path);
    }

    public function publicUrl(mixed $value): ?string
    {
        $path = $this->normalizePublicPath($value);
        if ($path === null) {
            return null;
        }

        $this->copyLegacyPublicFileIfNeeded($path);

        return Storage::disk(self::PUBLIC_DISK)->url($path);
    }

    public function publicPath(mixed $value): ?string
    {
        $path = $this->normalizePublicPath($value);
        if ($path === null) {
            return null;
        }

        $this->copyLegacyPublicFileIfNeeded($path);

        return Storage::disk(self::PUBLIC_DISK)->exists($path)
            ? Storage::disk(self::PUBLIC_DISK)->path($path)
            : null;
    }

    public function privatePath(mixed $value): ?string
    {
        $path = $this->normalizePrivatePath($value);
        if ($path === null) {
            return null;
        }

        return Storage::disk(self::PRIVATE_DISK)->exists($path)
            ? Storage::disk(self::PRIVATE_DISK)->path($path)
            : null;
    }

    public function privateUrl(mixed $value, string $scope = 'staff'): ?string
    {
        $path = $this->normalizePrivatePath($value);
        if ($path === null) {
            return null;
        }

        return route('media.private.show', [
            'scope' => $scope,
            'path' => $path,
        ]);
    }

    public function privateExists(mixed $value): bool
    {
        $path = $this->normalizePrivatePath($value);
        if ($path === null) {
            return false;
        }

        return Storage::disk(self::PRIVATE_DISK)->exists($path);
    }

    public function streamPrivate(mixed $value): ?StreamedResponse
    {
        $path = $this->normalizePrivatePath($value);
        if ($path === null || ! Storage::disk(self::PRIVATE_DISK)->exists($path)) {
            return null;
        }

        $stream = Storage::disk(self::PRIVATE_DISK)->readStream($path);
        if ($stream === false) {
            return null;
        }

        $mime = Storage::disk(self::PRIVATE_DISK)->mimeType($path) ?: 'application/octet-stream';
        $filename = basename($path);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function normalizePublicPath(mixed $value): ?string
    {
        $path = $this->normalizePath($value);
        if ($path === null) {
            return null;
        }

        $normalized = $path;

        $replacePrefixes = [
            'storage/uploads/images/' => '',
            'uploads/images/' => '',
            'storage/uploads/categories/' => 'categories/',
            'uploads/categories/' => 'categories/',
            'storage/storefront/categories/' => 'categories/',
            'storefront/categories/' => 'categories/',
            'storage/' => '',
            'uploads/' => '',
        ];

        foreach ($replacePrefixes as $prefix => $replacement) {
            if (! str_starts_with($normalized, $prefix)) {
                continue;
            }

            $normalized = $replacement.substr($normalized, strlen($prefix));
            break;
        }

        return $this->normalizePath($normalized);
    }

    public function normalizePrivatePath(mixed $value): ?string
    {
        $path = $this->normalizePath($value);
        if ($path === null) {
            return null;
        }

        $prefixes = [
            'private/',
            'app/private/',
            'storage/app/private/',
        ];

        foreach ($prefixes as $prefix) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }

            $path = substr($path, strlen($prefix));
            break;
        }

        return $this->normalizePath($path);
    }

    public function ensurePublicDirectory(string $directory = ''): void
    {
        $relative = $this->normalizePath($directory) ?? '';
        File::ensureDirectoryExists(Storage::disk(self::PUBLIC_DISK)->path($relative));
    }

    public function ensurePrivateDirectory(string $directory = ''): void
    {
        $relative = $this->normalizePath($directory) ?? '';
        File::ensureDirectoryExists(Storage::disk(self::PRIVATE_DISK)->path($relative));
    }

    protected function store(mixed $upload, string $disk, string $directory, bool $isPublic): string
    {
        if (! $upload instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'upload' => 'The uploaded file is invalid.',
            ]);
        }

        $extension = $this->resolveExtension($upload);
        $targetDirectory = $this->buildDatedDirectory($directory);
        $filename = (string) Str::uuid().'.'.$extension;

        if ($isPublic) {
            $this->ensurePublicDirectory($targetDirectory);
        } else {
            $this->ensurePrivateDirectory($targetDirectory);
        }

        $storedPath = Storage::disk($disk)->putFileAs($targetDirectory, $upload, $filename);

        if (! is_string($storedPath) || trim($storedPath) === '') {
            throw ValidationException::withMessages([
                'upload' => 'Image upload failed. Please try again.',
            ]);
        }

        /** @var string $normalized */
        $normalized = $this->normalizePath($storedPath) ?? $storedPath;

        return $normalized;
    }

    protected function buildDatedDirectory(string $directory): string
    {
        $base = $this->normalizePath($directory) ?? 'general';
        $dateFolder = now()->format('Y/m');

        return trim($base.'/'.$dateFolder, '/');
    }

    protected function resolveExtension(UploadedFile $upload): string
    {
        $mime = strtolower((string) $upload->getMimeType());

        if (! array_key_exists($mime, self::ALLOWED_MIME_TYPES)) {
            throw ValidationException::withMessages([
                'upload' => 'Unsupported image format. Allowed formats: jpg, jpeg, png, webp.',
            ]);
        }

        return self::ALLOWED_MIME_TYPES[$mime];
    }

    protected function normalizePath(mixed $value): ?string
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

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..'
        ));

        $normalized = implode('/', $segments);

        return $normalized === '' ? null : $normalized;
    }

    protected function copyLegacyPublicFileIfNeeded(string $normalizedPath): void
    {
        $publicDisk = Storage::disk(self::PUBLIC_DISK);
        if ($publicDisk->exists($normalizedPath)) {
            return;
        }

        if ($this->copyFromLegacyPublicDisk($normalizedPath, $publicDisk)) {
            return;
        }

        $this->copyFromLegacyPublicPaths($normalizedPath, $publicDisk);
    }

    protected function copyFromLegacyPublicDisk(string $normalizedPath, mixed $targetDisk): bool
    {
        $legacyDisk = Storage::disk('public');
        foreach ($this->legacyPublicDiskCandidates($normalizedPath) as $candidate) {
            if (! $legacyDisk->exists($candidate)) {
                continue;
            }

            $stream = $legacyDisk->readStream($candidate);
            if ($stream === false) {
                continue;
            }

            try {
                return (bool) $targetDisk->put($normalizedPath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        return false;
    }

    protected function copyFromLegacyPublicPaths(string $normalizedPath, mixed $targetDisk): bool
    {
        foreach ($this->legacyAbsolutePathCandidates($normalizedPath) as $candidate) {
            if (! File::exists($candidate) || ! File::isFile($candidate)) {
                continue;
            }

            $stream = fopen($candidate, 'rb');
            if ($stream === false) {
                continue;
            }

            try {
                return (bool) $targetDisk->put($normalizedPath, $stream);
            } finally {
                fclose($stream);
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function legacyPublicDiskCandidates(string $normalizedPath): array
    {
        $candidates = [
            $normalizedPath,
            'storefront/categories/'.$normalizedPath,
        ];

        if (str_starts_with($normalizedPath, 'categories/')) {
            $candidates[] = 'storefront/categories/'.substr($normalizedPath, strlen('categories/'));
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<int, string>
     */
    protected function legacyAbsolutePathCandidates(string $normalizedPath): array
    {
        $candidates = [
            public_path('uploads'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath)),
        ];

        if (str_starts_with($normalizedPath, 'categories/')) {
            $candidates[] = public_path('uploads'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath));
            $candidates[] = public_path('uploads'.DIRECTORY_SEPARATOR.'categories'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, substr($normalizedPath, strlen('categories/'))));
        } else {
            $candidates[] = public_path('uploads'.DIRECTORY_SEPARATOR.'categories'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath));
        }

        $legacyStorefrontRoot = config('filesystems.disks.storefront_uploads.root');
        if (is_string($legacyStorefrontRoot) && trim($legacyStorefrontRoot) !== '') {
            $legacyStorefrontRoot = rtrim($legacyStorefrontRoot, '/\\');
            $candidates[] = $legacyStorefrontRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
        }

        $legacyCategoryRoot = config('filesystems.disks.storefront_categories.root');
        if (is_string($legacyCategoryRoot) && trim($legacyCategoryRoot) !== '') {
            $legacyCategoryRoot = rtrim($legacyCategoryRoot, '/\\');
            $categoryPath = str_starts_with($normalizedPath, 'categories/')
                ? substr($normalizedPath, strlen('categories/'))
                : $normalizedPath;
            $candidates[] = $legacyCategoryRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $categoryPath);
        }

        return array_values(array_unique($candidates));
    }
}

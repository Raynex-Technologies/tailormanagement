<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InstallationState
{
    public function isInstalled(): bool
    {
        if ($this->shouldSkipChecks()) {
            return true;
        }

        if ($this->hasLockFile()) {
            return true;
        }

        if (blank(config('app.key'))) {
            return false;
        }

        return $this->databaseLooksInstalled();
    }

    public function bootstrapPreInstallRuntime(): void
    {
        if ($this->isInstalled()) {
            return;
        }

        if (blank(config('app.key'))) {
            config([
                'app.key' => $this->temporaryAppKey(),
            ]);
        }

        config([
            'cache.default' => 'file',
            'session.driver' => 'file',
            'session.connection' => null,
        ]);
    }

    public function hasLockFile(): bool
    {
        return File::exists($this->lockFilePath());
    }

    public function lockFilePath(): string
    {
        return (string) config('install.lock_file', storage_path('app/installed'));
    }

    public function markAsInstalled(): void
    {
        File::ensureDirectoryExists(dirname($this->lockFilePath()));
        File::put($this->lockFilePath(), now()->toIso8601String().PHP_EOL);
    }

    public function temporaryAppKey(): string
    {
        return 'base64:'.base64_encode(
            hash('sha256', 'tailor-install:'.base_path(), true)
        );
    }

    protected function shouldSkipChecks(): bool
    {
        return app()->runningUnitTests() && (bool) config('install.skip_during_tests', true);
    }

    protected function databaseLooksInstalled(): bool
    {
        try {
            DB::connection()->getPdo();

            if (! Schema::hasTable('users') || ! Schema::hasTable('branches') || ! Schema::hasTable('business_settings')) {
                return false;
            }

            return DB::table('users')->exists()
                && DB::table('branches')->exists()
                && DB::table('business_settings')->exists();
        } catch (Throwable) {
            return false;
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateStorefrontCategoryImages extends Command
{
    protected $signature = 'storefront:migrate-category-images
        {--dry-run : Preview actions without updating records}';

    protected $description = 'Deprecated alias for media:normalize-image-paths. Normalizes legacy category image paths and migrates files to public uploads.';

    public function handle(): int
    {
        $this->components->warn(
            'storefront:migrate-category-images is deprecated. Use media:normalize-image-paths instead.'
        );

        return (int) $this->call('media:normalize-image-paths', [
            '--dry-run' => (bool) $this->option('dry-run'),
        ]);
    }
}

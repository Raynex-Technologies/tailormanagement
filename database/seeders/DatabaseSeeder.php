<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed only reference data here. Installation-specific records
        // are created by the first-run installer.
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        if (Branch::query()->exists()) {
            $this->call([
                InventoryCategoriesAndItemsSeeder::class,
            ]);
        }

        // Run demo seeder if in local/development environment
        // or if explicitly requested via: php artisan db:seed --class=DemoSeeder
        if (app()->environment('local', 'development', 'testing')) {
            if ($this->command->confirm('Do you want to seed demo data?', false)) {
                $this->call(DemoSeeder::class);
            }
        }
    }
}

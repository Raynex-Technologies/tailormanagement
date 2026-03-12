<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Always run core seeders
        $this->call([
            RolesAndPermissionsSeeder::class,
            BranchSeeder::class,
            InvoiceTemplateSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // Run demo seeder if in local/development environment
        // or if explicitly requested via: php artisan db:seed --class=DemoSeeder
        if (app()->environment('local', 'development', 'testing')) {
            if ($this->command->confirm('Do you want to seed demo data?', false)) {
                $this->call(DemoSeeder::class);
            }
        }
    }
}

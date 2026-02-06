<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-superadmin
                            {--name= : The name of the superadmin}
                            {--email= : The email of the superadmin}
                            {--password= : The password of the superadmin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a superadmin user with all permissions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Ensure the superadmin role exists
        if (! Role::where('name', 'superadmin')->exists()) {
            $this->error('Superadmin role does not exist. Please run the RolesAndPermissionsSeeder first.');
            $this->info('Run: php artisan db:seed --class=RolesAndPermissionsSeeder');

            return Command::FAILURE;
        }

        // Get credentials from options, env, or prompt
        $name = $this->option('name')
            ?? env('SUPERADMIN_NAME')
            ?? $this->ask('Enter superadmin name', 'Super Admin');

        $email = $this->option('email')
            ?? env('SUPERADMIN_EMAIL')
            ?? $this->ask('Enter superadmin email', 'admin@tailormanagement.test');

        $password = $this->option('password')
            ?? env('SUPERADMIN_PASSWORD')
            ?? $this->secret('Enter superadmin password');

        if (empty($password)) {
            $this->error('Password is required.');

            return Command::FAILURE;
        }

        // Check if user already exists
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($this->confirm("User with email {$email} already exists. Do you want to assign superadmin role?", true)) {
                $existingUser->assignRole('superadmin');
                $this->info("Superadmin role assigned to existing user: {$email}");

                return Command::SUCCESS;
            }

            return Command::FAILURE;
        }

        // Create new user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('superadmin');

        $this->info('');
        $this->info('Superadmin user created successfully!');
        $this->table(
            ['Name', 'Email', 'Role'],
            [[$name, $email, 'superadmin']]
        );

        return Command::SUCCESS;
    }
}

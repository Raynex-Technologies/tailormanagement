<?php

namespace Database\Seeders;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates a default superadmin user using environment variables or defaults.
     */
    public function run(): void
    {
        $name = env('SUPERADMIN_NAME', 'Super Admin');
        $email = env('SUPERADMIN_EMAIL', 'admin@tailormanagement.test');
        $password = env('SUPERADMIN_PASSWORD', 'password');

        // Check if user already exists
        $user = User::where('email', $email)->first();
        $isNewUser = false;

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $isNewUser = true;
        }

        // Assign superadmin role
        if (! $user->hasRole('superadmin')) {
            $user->assignRole('superadmin');
        }

        // Send welcome notification to new users
        if ($isNewUser) {
            $user->notify(new WelcomeNotification());
            $user->notify(new WelcomeNotification(
                'System Ready',
                'Phase 1 Foundation is complete. Your dashboard is ready to use.'
            ));
        }

        $this->command->info("SuperAdmin user created/verified: {$email}");
    }
}

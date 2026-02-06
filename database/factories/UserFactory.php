<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'branch_id' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Assign user to a specific branch.
     */
    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branch->id,
        ]);
    }

    /**
     * Create a superadmin user.
     */
    public function superadmin(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('superadmin');
        });
    }

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('admin');
        });
    }

    /**
     * Create a branch manager user.
     */
    public function branchManager(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('branch_manager');
        });
    }

    /**
     * Create a storekeeper user.
     */
    public function storekeeper(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('storekeeper');
        });
    }

    /**
     * Create an accountant user.
     */
    public function accountant(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('accountant');
        });
    }

    /**
     * Create a tailor user.
     */
    public function tailor(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('tailor');
        });
    }

    /**
     * Create a sales user.
     */
    public function sales(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('sales');
        });
    }
}

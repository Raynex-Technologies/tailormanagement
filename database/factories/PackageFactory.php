<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->randomFloat(2, 50000, 1500000),
            'duration_value' => fake()->numberBetween(1, 12),
            'duration_unit' => fake()->randomElement(['weeks', 'months']),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}

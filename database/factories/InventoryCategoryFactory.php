<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryCategory>
 */
class InventoryCategoryFactory extends Factory
{
    protected $model = InventoryCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Fabrics',
            'Threads',
            'Buttons',
            'Zippers',
            'Lining',
            'Interfacing',
            'Trims',
            'Needles',
            'Elastic',
            'Accessories',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}

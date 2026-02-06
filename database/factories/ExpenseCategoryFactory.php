<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Office Supplies',
            'Utilities',
            'Transportation',
            'Maintenance',
            'Marketing',
            'Equipment',
            'Fabric Purchase',
            'Staff Welfare',
        ];

        return [
            'name' => fake()->randomElement($categories) . ' ' . fake()->numerify('###'),
        ];
    }
}

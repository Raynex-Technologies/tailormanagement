<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'capital_allocation_id' => null,
            'expense_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'amount' => fake()->numberBetween(10000, 500000),
            'vendor' => fake()->company(),
            'reference' => fake()->optional()->numerify('EXP-####'),
            'note' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Link expense to a capital allocation.
     */
    public function withCapitalAllocation(int $allocationId): static
    {
        return $this->state(fn (array $attributes) => [
            'capital_allocation_id' => $allocationId,
        ]);
    }
}

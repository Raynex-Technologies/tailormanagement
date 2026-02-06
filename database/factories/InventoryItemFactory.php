<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $items = [
            ['name' => 'Cotton Fabric - White', 'unit' => 'meters', 'buy' => 5000, 'sell' => 7000],
            ['name' => 'Cotton Fabric - Black', 'unit' => 'meters', 'buy' => 5500, 'sell' => 7500],
            ['name' => 'Silk Fabric - Blue', 'unit' => 'meters', 'buy' => 15000, 'sell' => 20000],
            ['name' => 'Linen Fabric - Natural', 'unit' => 'meters', 'buy' => 8000, 'sell' => 12000],
            ['name' => 'Polyester Thread - White', 'unit' => 'spools', 'buy' => 500, 'sell' => 800],
            ['name' => 'Polyester Thread - Black', 'unit' => 'spools', 'buy' => 500, 'sell' => 800],
            ['name' => 'Buttons - Small Silver', 'unit' => 'pcs', 'buy' => 50, 'sell' => 100],
            ['name' => 'Buttons - Large Gold', 'unit' => 'pcs', 'buy' => 100, 'sell' => 200],
            ['name' => 'Zipper - 20cm Black', 'unit' => 'pcs', 'buy' => 800, 'sell' => 1500],
            ['name' => 'Zipper - 50cm Silver', 'unit' => 'pcs', 'buy' => 1200, 'sell' => 2000],
            ['name' => 'Elastic Band - 2cm', 'unit' => 'meters', 'buy' => 200, 'sell' => 400],
            ['name' => 'Interfacing - Medium', 'unit' => 'meters', 'buy' => 2000, 'sell' => 3500],
            ['name' => 'Lining - Black', 'unit' => 'meters', 'buy' => 3000, 'sell' => 5000],
            ['name' => 'Lining - White', 'unit' => 'meters', 'buy' => 3000, 'sell' => 5000],
            ['name' => 'Sewing Needles - Pack', 'unit' => 'packs', 'buy' => 1000, 'sell' => 1800],
        ];

        $item = fake()->randomElement($items);

        return [
            'inventory_category_id' => InventoryCategory::inRandomOrder()->first()?->id,
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'name' => $item['name'],
            'unit' => $item['unit'],
            'reorder_level' => fake()->numberBetween(5, 20),
            'default_buy_price' => $item['buy'],
            'default_sell_price' => $item['sell'],
            'is_active' => true,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (InventoryItem $item) {
            // Create initial stock record with same branch as item
            $item->stock()->create([
                'branch_id' => $item->branch_id,
                'qty_on_hand' => fake()->numberBetween(10, 100),
                'qty_reserved' => 0,
            ]);
        });
    }
}

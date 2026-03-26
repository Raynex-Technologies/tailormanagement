<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InventoryCategoriesAndItemsSeeder extends Seeder
{
    /**
     * Seed sample inventory categories and items for each branch.
     */
    public function run(): void
    {
        $canonicalBranchNames = collect(BranchSeeder::sampleBranches())
            ->pluck('name')
            ->all();

        $branches = Branch::query()
            ->whereIn('name', $canonicalBranchNames)
            ->orderBy('id')
            ->get();

        if ($branches->count() < 2) {
            $this->command->warn('Canonical branches are missing. Running BranchSeeder first...');
            $this->call(BranchSeeder::class);

            $branches = Branch::query()
                ->whereIn('name', $canonicalBranchNames)
                ->orderBy('id')
                ->get();
        }

        if ($branches->count() < 2) {
            $this->command->error('Unable to seed inventory samples because canonical branches were not found.');

            return;
        }

        $catalog = $this->catalog();

        $createdCategories = 0;
        $createdItems = 0;
        $createdStocks = 0;

        foreach ($branches as $branch) {
            $branchPrefix = $this->branchSkuPrefix($branch);

            foreach ($catalog as $categoryData) {
                $category = InventoryCategory::withoutBranchScope()->firstOrCreate(
                    ['branch_id' => $branch->id, 'slug' => $categoryData['slug']],
                    [
                        'branch_id' => $branch->id,
                        'name' => $categoryData['name'],
                        'slug' => $categoryData['slug'],
                    ]
                );

                if ($category->wasRecentlyCreated) {
                    $createdCategories++;
                }

                foreach ($categoryData['items'] as $itemData) {
                    $sku = "{$branchPrefix}-{$itemData['sku_code']}";
                    $unit = InventoryUnit::withoutBranchScope()->firstOrCreate(
                        ['branch_id' => $branch->id, 'name' => $itemData['unit']],
                        [
                            'branch_id' => $branch->id,
                            'name' => $itemData['unit'],
                        ]
                    );

                    $item = InventoryItem::withoutBranchScope()->firstOrCreate(
                        ['sku' => $sku],
                        [
                            'branch_id' => $branch->id,
                            'inventory_category_id' => $category->id,
                            'inventory_unit_id' => $unit->id,
                            'sku' => $sku,
                            'name' => $itemData['name'],
                            'unit' => $unit->name,
                            'reorder_level' => $itemData['reorder_level'],
                            'default_buy_price' => $itemData['buy_price'],
                            'default_sell_price' => $itemData['sell_price'],
                            'is_active' => true,
                        ]
                    );

                    if ($item->inventory_unit_id !== $unit->id || $item->unit !== $unit->name) {
                        $item->update([
                            'inventory_unit_id' => $unit->id,
                            'unit' => $unit->name,
                        ]);
                    }

                    if ($item->wasRecentlyCreated) {
                        $createdItems++;
                    }

                    $stock = InventoryStock::withoutBranchScope()->firstOrCreate(
                        ['inventory_item_id' => $item->id],
                        [
                            'branch_id' => $branch->id,
                            'inventory_item_id' => $item->id,
                            'qty_on_hand' => $itemData['qty_on_hand'],
                            'qty_reserved' => 0,
                        ]
                    );

                    if ($stock->wasRecentlyCreated) {
                        $createdStocks++;
                    }
                }
            }
        }

        $this->command->info("Inventory seeding complete. Categories created: {$createdCategories}, items created: {$createdItems}, stock rows created: {$createdStocks}.");
    }

    /**
     * Build a stable SKU prefix from branch code (fallback to branch id).
     */
    protected function branchSkuPrefix(Branch $branch): string
    {
        $code = (string) ($branch->code ?? '');
        $normalized = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        if ($normalized === '') {
            return 'BRANCH' . $branch->id;
        }

        return $normalized;
    }

    /**
     * Category + item sample data used for every branch.
     */
    protected function catalog(): array
    {
        return [
            [
                'name' => 'Fabrics',
                'slug' => 'fabrics',
                'items' => [
                    ['sku_code' => 'FAB-COT-WHT', 'name' => 'Cotton Fabric - White', 'unit' => 'meters', 'reorder_level' => 25, 'buy_price' => 5000, 'sell_price' => 7000, 'qty_on_hand' => 140],
                    ['sku_code' => 'FAB-COT-BLK', 'name' => 'Cotton Fabric - Black', 'unit' => 'meters', 'reorder_level' => 20, 'buy_price' => 5200, 'sell_price' => 7200, 'qty_on_hand' => 120],
                    ['sku_code' => 'FAB-LIN-NAT', 'name' => 'Linen Fabric - Natural', 'unit' => 'meters', 'reorder_level' => 15, 'buy_price' => 9000, 'sell_price' => 12500, 'qty_on_hand' => 90],
                ],
            ],
            [
                'name' => 'Threads',
                'slug' => 'threads',
                'items' => [
                    ['sku_code' => 'THR-POL-WHT', 'name' => 'Polyester Thread - White', 'unit' => 'spools', 'reorder_level' => 30, 'buy_price' => 500, 'sell_price' => 800, 'qty_on_hand' => 220],
                    ['sku_code' => 'THR-POL-BLK', 'name' => 'Polyester Thread - Black', 'unit' => 'spools', 'reorder_level' => 30, 'buy_price' => 500, 'sell_price' => 800, 'qty_on_hand' => 210],
                    ['sku_code' => 'THR-COT-NVY', 'name' => 'Cotton Thread - Navy', 'unit' => 'spools', 'reorder_level' => 20, 'buy_price' => 650, 'sell_price' => 950, 'qty_on_hand' => 150],
                ],
            ],
            [
                'name' => 'Buttons & Fasteners',
                'slug' => 'buttons-fasteners',
                'items' => [
                    ['sku_code' => 'BTN-SLV-SM', 'name' => 'Buttons - Small Silver', 'unit' => 'pcs', 'reorder_level' => 100, 'buy_price' => 50, 'sell_price' => 100, 'qty_on_hand' => 900],
                    ['sku_code' => 'BTN-GLD-LG', 'name' => 'Buttons - Large Gold', 'unit' => 'pcs', 'reorder_level' => 80, 'buy_price' => 100, 'sell_price' => 200, 'qty_on_hand' => 700],
                    ['sku_code' => 'SNP-MTL-MD', 'name' => 'Metal Snap Fasteners - Medium', 'unit' => 'sets', 'reorder_level' => 60, 'buy_price' => 300, 'sell_price' => 550, 'qty_on_hand' => 260],
                ],
            ],
            [
                'name' => 'Zippers',
                'slug' => 'zippers',
                'items' => [
                    ['sku_code' => 'ZIP-20-BLK', 'name' => 'Zipper - 20cm Black', 'unit' => 'pcs', 'reorder_level' => 40, 'buy_price' => 800, 'sell_price' => 1500, 'qty_on_hand' => 320],
                    ['sku_code' => 'ZIP-50-SLV', 'name' => 'Zipper - 50cm Silver', 'unit' => 'pcs', 'reorder_level' => 30, 'buy_price' => 1200, 'sell_price' => 2000, 'qty_on_hand' => 240],
                    ['sku_code' => 'ZIP-30-NVY', 'name' => 'Zipper - 30cm Navy', 'unit' => 'pcs', 'reorder_level' => 35, 'buy_price' => 950, 'sell_price' => 1700, 'qty_on_hand' => 280],
                ],
            ],
            [
                'name' => 'Accessories',
                'slug' => 'accessories',
                'items' => [
                    ['sku_code' => 'ELS-2CM', 'name' => 'Elastic Band - 2cm', 'unit' => 'meters', 'reorder_level' => 40, 'buy_price' => 200, 'sell_price' => 400, 'qty_on_hand' => 500],
                    ['sku_code' => 'NED-PCK', 'name' => 'Sewing Needles - Pack', 'unit' => 'packs', 'reorder_level' => 20, 'buy_price' => 1000, 'sell_price' => 1800, 'qty_on_hand' => 180],
                    ['sku_code' => 'INT-MDM', 'name' => 'Interfacing - Medium', 'unit' => 'meters', 'reorder_level' => 15, 'buy_price' => 2000, 'sell_price' => 3500, 'qty_on_hand' => 130],
                ],
            ],
        ];
    }
}

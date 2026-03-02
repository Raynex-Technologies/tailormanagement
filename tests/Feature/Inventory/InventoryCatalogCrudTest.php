<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\Categories\Index as CategoriesIndex;
use App\Livewire\Inventory\Items\Index as ItemsIndex;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryCatalogCrudTest extends TestCase
{
    public function test_branch_manager_can_create_category_without_providing_a_slug(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        Livewire::test(CategoriesIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Buttons & Fasteners')
            ->set('slug', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('inventory_categories', [
            'branch_id' => $this->branch->id,
            'name' => 'Buttons & Fasteners',
            'slug' => 'buttons-fasteners',
        ]);
    }

    public function test_branch_manager_can_create_item_without_providing_a_sku(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $this->branch->update(['code' => 'DSM-01']);

        $category = InventoryCategory::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
        $unit = InventoryUnit::create([
            'branch_id' => $this->branch->id,
            'name' => 'meters',
        ]);

        Livewire::test(ItemsIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Cotton Fabric White')
            ->set('sku', '')
            ->set('inventory_category_id', $category->id)
            ->set('inventory_unit_id', $unit->id)
            ->set('reorder_level', 10)
            ->call('saveItem')
            ->assertHasNoErrors();

        $item = InventoryItem::query()
            ->where('branch_id', $this->branch->id)
            ->where('name', 'Cotton Fabric White')
            ->first();

        $this->assertNotNull($item);
        $this->assertSame('DSM01-COT-FAB-WHI', $item->sku);
    }
}

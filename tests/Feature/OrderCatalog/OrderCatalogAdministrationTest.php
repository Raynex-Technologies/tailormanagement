<?php

namespace Tests\Feature\OrderCatalog;

use App\Enums\OrderCatalogItemType;
use App\Livewire\OrderCatalog\Index;
use App\Livewire\OrderCatalog\ItemForm;
use App\Livewire\OrderCatalog\PackageForm;
use App\Models\InventoryItem;
use App\Models\OrderCatalogItem;
use App\Models\OrderPackageTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCatalogAdministrationTest extends TestCase
{
    public function test_authorized_staff_can_open_catalog_and_unauthorized_staff_are_rejected(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('order-catalog.index'))->assertOk()->assertSee('Order Catalog');

        auth()->logout();
        $this->actingAsRole('tailor');
        $this->get(route('order-catalog.index'))->assertForbidden();
    }

    public function test_staff_can_create_garment_and_service_with_domain_defaults(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(ItemForm::class)
            ->set('name', 'Executive Suit')
            ->set('defaultSellingPrice', '500000')
            ->set('availableAllBranches', true)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(ItemForm::class)
            ->set('name', 'Express Alteration')
            ->set('type', 'service')
            ->set('defaultSellingPrice', '25000')
            ->set('availableAllBranches', true)
            ->call('save')
            ->assertHasNoErrors();

        $garment = OrderCatalogItem::query()->where('name', 'Executive Suit')->firstOrFail();
        $service = OrderCatalogItem::query()->where('name', 'Express Alteration')->firstOrFail();
        $this->assertSame('individual', $garment->quantity_behavior->value);
        $this->assertTrue($garment->requires_measurements);
        $this->assertSame('bulk', $service->quantity_behavior->value);
        $this->assertFalse($service->requires_measurements);
    }

    public function test_item_can_be_edited_archived_and_reactivated(): void
    {
        $this->actingAsRole('admin');
        $item = $this->catalogItem();

        Livewire::test(ItemForm::class, ['catalogItem' => $item])
            ->set('name', 'Updated Suit')
            ->set('defaultSellingPrice', '525000')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(Index::class)
            ->call('archiveItem', $item->id)
            ->assertHasNoErrors()
            ->call('reactivateItem', $item->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('order_catalog_items', [
            'id' => $item->id,
            'name' => 'Updated Suit',
            'default_selling_price' => '525000.00',
            'archived_at' => null,
        ]);
    }

    public function test_all_branch_and_selected_branch_item_availability_are_saved_safely(): void
    {
        $this->actingAsRole('admin');
        Livewire::test(ItemForm::class)
            ->set('name', 'Global Service')
            ->set('type', 'service')
            ->set('defaultSellingPrice', '10000')
            ->set('availableAllBranches', true)
            ->call('save')->assertHasNoErrors();

        Livewire::test(ItemForm::class)
            ->set('name', 'Branch Garment')
            ->set('defaultSellingPrice', '100000')
            ->set('availableAllBranches', false)
            ->set('branchIds', [$this->otherBranch->id])
            ->call('save')->assertHasNoErrors();

        $global = OrderCatalogItem::query()->where('name', 'Global Service')->firstOrFail();
        $selected = OrderCatalogItem::query()->where('name', 'Branch Garment')->firstOrFail();
        $this->assertTrue($global->available_all_branches);
        $this->assertSame([$this->otherBranch->id], $selected->branches()->pluck('branches.id')->all());
    }

    public function test_branch_manager_cannot_expose_catalog_content_outside_their_branch(): void
    {
        $this->actingAsRole('branch_manager');

        Livewire::test(ItemForm::class)
            ->set('name', 'Scoped Suit')
            ->set('defaultSellingPrice', '100000')
            ->set('availableAllBranches', true)
            ->set('branchIds', [$this->otherBranch->id])
            ->call('save')
            ->assertHasErrors('branchIds');

        Livewire::test(ItemForm::class)
            ->set('name', 'Scoped Suit')
            ->set('defaultSellingPrice', '100000')
            ->set('availableAllBranches', false)
            ->set('branchIds', [$this->otherBranch->id])
            ->call('save')
            ->assertHasNoErrors();

        $item = OrderCatalogItem::query()->where('name', 'Scoped Suit')->firstOrFail();
        $this->assertSame([$this->branch->id], $item->branches()->pluck('branches.id')->all());
    }

    public function test_package_builder_creates_catalog_inventory_and_mixed_components_with_canonical_pricing(): void
    {
        $this->actingAsRole('admin');
        $suit = $this->catalogItem(['default_selling_price' => '500000.00']);
        $tie = $this->inventoryItem(['default_sell_price' => '40000.00']);

        Livewire::test(PackageForm::class)
            ->set('name', 'Wedding Package')
            ->set('availableAllBranches', false)
            ->set('branchIds', [$this->branch->id])
            ->call('addCatalogItem', $suit->id)
            ->call('addInventoryItem', $tie->id)
            ->set('components.0.default_quantity', '2')
            ->set('components.0.minimum_quantity', '1')
            ->set('components.0.maximum_quantity', '2')
            ->set('components.0.package_unit_price', '450000')
            ->set('components.1.default_quantity', '2')
            ->set('components.1.minimum_quantity', '0')
            ->set('components.1.maximum_quantity', '3')
            ->set('components.1.package_unit_price', '30000')
            ->assertViewHas('pricingSummary', fn ($summary) => $summary === [
                'standard_value' => '1080000.00',
                'package_price' => '960000.00',
                'difference' => '120000.00',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $package = OrderPackageTemplate::query()->where('name', 'Wedding Package')->firstOrFail();
        $this->assertCount(2, $package->items);
        $this->assertNotNull($package->items[0]->order_catalog_item_id);
        $this->assertNotNull($package->items[1]->inventory_item_id);
        $this->assertSame('960000.00', $package->defaultTotal());
        $this->assertSame('120000.00', $package->savings());
    }

    public function test_package_builder_enforces_quantity_rules(): void
    {
        $this->actingAsRole('admin');
        $item = $this->catalogItem();

        Livewire::test(PackageForm::class)
            ->set('name', 'Invalid Package')
            ->set('availableAllBranches', true)
            ->call('addCatalogItem', $item->id)
            ->set('components.0.minimum_quantity', '2')
            ->set('components.0.default_quantity', '1')
            ->set('components.0.maximum_quantity', '3')
            ->call('save')
            ->assertHasErrors('components');
    }

    public function test_meaningful_package_change_increments_revision_but_no_op_save_does_not(): void
    {
        $this->actingAsRole('admin');
        $package = $this->packageWithCatalogComponent();

        Livewire::test(PackageForm::class, ['package' => $package])->call('save')->assertHasNoErrors();
        $this->assertSame(1, $package->refresh()->revision);

        Livewire::test(PackageForm::class, ['package' => $package])
            ->set('components.0.package_unit_price', '475000')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(2, $package->refresh()->revision);
    }

    public function test_package_can_be_archived_and_reactivated(): void
    {
        $this->actingAsRole('admin');
        $package = $this->packageWithCatalogComponent();

        Livewire::test(Index::class, ['tab' => 'packages'])
            ->call('archivePackage', $package->id)
            ->call('reactivatePackage', $package->id)
            ->assertHasNoErrors();

        $this->assertNull($package->refresh()->archived_at);
    }

    public function test_archived_catalog_item_cannot_be_newly_added_but_existing_component_renders_safely(): void
    {
        $this->actingAsRole('admin');
        $package = $this->packageWithCatalogComponent();
        $item = $package->items->first()->catalogItem;
        $item->archive();

        Livewire::test(PackageForm::class, ['package' => $package])
            ->assertSee($item->name)
            ->assertSee('Historical source');

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(PackageForm::class)->call('addCatalogItem', $item->id);
    }

    public function test_inventory_component_is_rejected_for_all_branch_or_wrong_branch_package(): void
    {
        $this->actingAsRole('admin');
        $inventory = $this->inventoryItem();

        Livewire::test(PackageForm::class)
            ->set('name', 'Invalid Global Inventory Package')
            ->set('availableAllBranches', false)
            ->set('branchIds', [$this->branch->id])
            ->call('addInventoryItem', $inventory->id)
            ->set('availableAllBranches', true)
            ->call('save')
            ->assertHasErrors('components');

        Livewire::test(PackageForm::class)
            ->set('name', 'Invalid Branch Inventory Package')
            ->set('availableAllBranches', false)
            ->set('branchIds', [$this->branch->id])
            ->call('addInventoryItem', $inventory->id)
            ->set('branchIds', [$this->otherBranch->id])
            ->call('save')
            ->assertHasErrors('components');
    }

    private function catalogItem(array $attributes = []): OrderCatalogItem
    {
        return OrderCatalogItem::create([
            'name' => 'Two-Piece Suit',
            'type' => OrderCatalogItemType::Garment,
            'default_selling_price' => '500000.00',
            'requires_measurements' => true,
            'available_all_branches' => true,
            ...$attributes,
        ]);
    }

    private function inventoryItem(array $attributes = []): InventoryItem
    {
        return InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Silk Tie',
            'default_sell_price' => '40000.00',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function packageWithCatalogComponent(): OrderPackageTemplate
    {
        $item = $this->catalogItem();
        $package = OrderPackageTemplate::create([
            'name' => 'Wedding Package',
            'available_all_branches' => true,
        ]);
        $package->items()->create([
            'order_catalog_item_id' => $item->id,
            'minimum_quantity' => '1.00',
            'default_quantity' => '1.00',
            'maximum_quantity' => '2.00',
            'package_unit_price' => '450000.00',
            'sort_order' => 1,
        ]);

        return $package->fresh(['items.catalogItem']);
    }
}

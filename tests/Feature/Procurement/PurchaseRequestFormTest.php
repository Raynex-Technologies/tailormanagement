<?php

namespace Tests\Feature\Procurement;

use App\Livewire\Procurement\Requests\Form as PurchaseRequestForm;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseRequestFormTest extends TestCase
{
    public function test_purchase_request_form_uses_live_search_bindings(): void
    {
        $this->actingAsRole('admin', $this->branch);

        $this->get(route('procurement.requests.create'))
            ->assertOk()
            ->assertSee('wire:model.live.debounce.300ms="productSearch"', false)
            ->assertSee('wire:model.live="branchId"', false);
    }

    public function test_purchase_request_search_only_returns_items_for_the_active_branch(): void
    {
        $branchCategory = InventoryCategory::factory()->create(['branch_id' => $this->branch->id]);
        $otherBranchCategory = InventoryCategory::factory()->create(['branch_id' => $this->otherBranch->id]);

        $branchItem = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'inventory_category_id' => $branchCategory->id,
            'name' => 'Navy Cotton Fabric',
            'sku' => 'FAB-NAVY-01',
        ]);

        InventoryItem::factory()->create([
            'branch_id' => $this->otherBranch->id,
            'inventory_category_id' => $otherBranchCategory->id,
            'name' => 'Navy Cotton Fabric Other',
            'sku' => 'FAB-NAVY-02',
        ]);

        $this->actingAsRole('storekeeper', $this->branch);

        Livewire::test(PurchaseRequestForm::class)
            ->set('productSearch', 'Navy')
            ->assertSet('showSearchDropdown', true)
            ->assertSee($branchItem->name)
            ->assertDontSee('Navy Cotton Fabric Other');
    }

    public function test_changing_branch_removes_inventory_items_but_keeps_manual_items(): void
    {
        $this->actingAsRole('admin', $this->branch);

        $branchCategory = InventoryCategory::factory()->create(['branch_id' => $this->branch->id]);
        $inventoryItem = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'inventory_category_id' => $branchCategory->id,
            'name' => 'Premium Wool',
            'sku' => 'WOOL-001',
        ]);

        Livewire::test(PurchaseRequestForm::class)
            ->set('items', [
                [
                    'id' => null,
                    'inventory_item_id' => $inventoryItem->id,
                    'item_name' => $inventoryItem->name,
                    'sku' => $inventoryItem->sku,
                    'qty' => 1,
                    'unit_price_est' => 12500,
                    'current_stock' => 24,
                ],
                [
                    'id' => null,
                    'inventory_item_id' => null,
                    'item_name' => 'Manual Trim',
                    'sku' => null,
                    'qty' => 2,
                    'unit_price_est' => 300,
                    'current_stock' => null,
                ],
            ])
            ->set('branchId', $this->otherBranch->id)
            ->assertSee('Changing the branch removed 1 inventory item from this draft.')
            ->assertSet('items.0.item_name', 'Manual Trim')
            ->assertSet('items.0.inventory_item_id', null)
            ->assertDontSee('Premium Wool');
    }

    public function test_create_draft_rejects_inventory_items_from_another_branch(): void
    {
        $otherBranchCategory = InventoryCategory::factory()->create(['branch_id' => $this->otherBranch->id]);
        $otherBranchItem = InventoryItem::factory()->create([
            'branch_id' => $this->otherBranch->id,
            'inventory_category_id' => $otherBranchCategory->id,
            'name' => 'Off-Branch Fabric',
            'sku' => 'OFF-001',
        ]);

        $user = $this->actingAsRole('storekeeper', $this->branch);

        $this->expectException(ValidationException::class);

        app(PurchaseRequestService::class)->createDraft($user, [
            'items' => [
                [
                    'inventory_item_id' => $otherBranchItem->id,
                    'item_name' => $otherBranchItem->name,
                    'qty' => 1,
                    'unit_price_est' => 5000,
                ],
            ],
        ]);
    }
}

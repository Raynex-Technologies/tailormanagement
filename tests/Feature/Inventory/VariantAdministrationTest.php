<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryItemOptionValue;
use App\Models\InventoryTransaction;
use App\Services\Inventory\StockUnitReconciliationService;
use App\Services\Inventory\StockUnitResolver;
use App\Services\Inventory\VariantAdministrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VariantAdministrationTest extends TestCase
{
    private VariantAdministrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsRole('superadmin', $this->branch);
        $this->service = app(VariantAdministrationService::class);
    }

    private function product(): InventoryItem
    {
        $item = InventoryItem::factory()->create(['branch_id' => $this->branch->id, 'default_sell_price' => 35000, 'default_buy_price' => 20000, 'is_active' => true]);
        $item->stock->update(['qty_on_hand' => 10, 'qty_reserved' => 0]);

        return $item;
    }

    private function choices(InventoryItem $item): array
    {
        $color = $this->service->option($item->id, null, 'Color');
        $size = $this->service->option($item->id, null, 'Size', 1);
        $values = [];
        foreach (['Black', 'White'] as $name) {
            $values[$name] = $this->service->value($item->id, $color->id, null, $name);
        }
        foreach (['Small', 'Medium', 'Large'] as $name) {
            $values[$name] = $this->service->value($item->id, $size->id, null, $name);
        }

        return $values;
    }

    private function reject(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected safe rejection');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_plain_tshirt_sparse_allocation_and_exact_stock_actions(): void
    {
        $item = $this->product();
        $values = $this->choices($item);
        $allocation = [];
        foreach ([['Black', 'Small', 2], ['Black', 'Medium', 3], ['Black', 'Large', 1], ['White', 'Small', 1], ['White', 'Large', 3]] as [$c,$s,$qty]) {
            $v = $this->service->combination($item->id, [$values[$c]->id, $values[$s]->id], $c.'-'.$s);
            $allocation[$v->id] = (string) $qty;
        }
        $this->assertSame(5, $item->variants()->count());
        $this->assertSame(6, $item->stockUnits()->count());
        $this->service->convert($item->id, $allocation, '10');
        $item->refresh();
        $this->get(route('inventory.items.variations', $item->id))->assertOk()->assertSee('Product with variations');
        $this->assertSame('variants', $item->variant_mode);
        $this->assertSame('10.00', $item->physical_on_hand);
        $black = $item->variants()->whereHas('selectedValues', fn ($q) => $q->where('inventory_item_option_values.id', $values['Black']->id))->with('stockUnit.stock')->get();
        $this->assertEquals(6, $black->sum(fn ($v) => $v->stockUnit->stock->qty_on_hand));
        $this->assertEquals(0, $item->simpleStockUnit->stock->qty_on_hand);
        $variant = $item->variants()->first();
        $this->assertNotNull(app(StockUnitResolver::class)->bySku($variant->sku));
        $this->service->stock($item->id, $variant->id, 'receive', '3', '20000', 'Receipt');
        $this->service->stock($item->id, $variant->id, 'adjust', '-1', null, 'Count correction');
        $this->assertSame('12.00', $item->fresh()->physical_on_hand);
        $report = app(StockUnitReconciliationService::class)->inspect();
        $this->assertSame(0, $report['summary']['unit_balance_mismatches']);
        $this->assertSame(0, $report['summary']['duplicate_simple_units']);
        $this->assertSame(0, $report['summary']['items_requiring_allocation']);
    }

    public function test_rename_reorder_and_delete_rules_preserve_ids(): void
    {
        $item = $this->product();
        $values = $this->choices($item);
        $v = $this->service->combination($item->id, [$values['Black']->id, $values['Medium']->id], 'BLACK-M');
        $unit = $v->stockUnit->id;
        $this->service->value($item->id, $values['Medium']->inventory_item_option_id, $values['Medium']->id, 'M', 7);
        $this->assertSame($v->id, $item->variants()->first()->id);
        $this->assertSame($unit, $v->fresh()->stockUnit->id);
        $this->assertSame('Black / M', $v->fresh()->display_name);
        $this->assertSame(7, $values['Medium']->fresh()->sort_order);
        $this->reject(fn () => $this->service->deleteValue($item->id, $values['Medium']->id));
        $this->service->deleteValue($item->id, $values['Large']->id);
        $this->assertNull(InventoryItemOptionValue::find($values['Large']->id));
    }

    public function test_duplicate_combination_and_cross_product_values_fail_atomically(): void
    {
        $item = $this->product();
        $values = $this->choices($item);
        $ids = [$values['Black']->id, $values['Small']->id];
        $this->service->combination($item->id, $ids, 'FIRST');
        $this->reject(fn () => $this->service->combination($item->id, array_reverse($ids), 'SECOND'));
        $other = $this->product();
        $alien = $this->choices($other);
        $this->reject(fn () => $this->service->combination($item->id, [$alien['Black']->id, $values['Small']->id], 'ALIEN'));
        $this->assertSame(1, $item->variants()->count());
    }

    public function test_conversion_rejects_incomplete_excess_stale_and_reserved_allocations(): void
    {
        $item = $this->product();
        $values = $this->choices($item);
        $v = $this->service->combination($item->id, [$values['Black']->id, $values['Small']->id], 'ONE');
        foreach (['9', '11'] as $qty) {
            $this->reject(fn () => $this->service->convert($item->id, [$v->id => $qty], '10'));
        }
        $this->reject(fn () => $this->service->convert($item->id, [$v->id => '10'], '9'));
        $item->stock->update(['qty_reserved' => 1]);
        $this->reject(fn () => $this->service->convert($item->id, [$v->id => '10'], '10'));
        $this->assertSame('simple', $item->fresh()->variant_mode);
        $this->assertEquals(10, $item->stock->fresh()->qty_on_hand);
        $this->assertSame(0, DB::table('inventory_variant_allocations')->count());
    }

    public function test_pricing_sku_and_barcode_are_explicit_and_unique(): void
    {
        $item = $this->product();
        $values = $this->choices($item);
        $v = $this->service->combination($item->id, [$values['Black']->id, $values['Small']->id], 'FIRST');
        $this->assertSame('35000.00', $v->stockUnit->selling_price);
        $this->service->identity($item->id, $v->id, 'EDITED', '42000', '23000', '629123');
        $item->update(['default_sell_price' => 99999]);
        $this->assertSame('42000.00', $v->fresh()->stockUnit->selling_price);
        $this->assertSame('23000.00', $v->fresh()->stockUnit->reference_cost);
        $other = $this->service->combination($item->id, [$values['White']->id, $values['Small']->id], 'SECOND');
        $this->reject(fn () => $this->service->identity($item->id, $other->id, 'EDITED', '1', '1', null));
        $this->reject(fn () => $this->service->identity($item->id, $other->id, 'SECOND', '1', '1', '629123'));
        $this->assertSame('99999.00', $other->fresh()->stockUnit->selling_price);
    }

    public function test_retirement_blocks_stock_and_reactivation_preserves_identity(): void
    {
        $item = $this->product();
        $values = $this->choices($item);
        $v = $this->service->combination($item->id, [$values['Black']->id, $values['Small']->id], 'ONE');
        $unit = $v->stockUnit->id;
        $this->service->active($item->id, $v->id, false);
        $this->service->active($item->id, $v->id, true);
        $this->assertSame($unit, $v->fresh()->stockUnit->id);
        $this->service->convert($item->id, [$v->id => '10'], '10');
        $this->reject(fn () => $this->service->active($item->id, $v->id, false));
        $this->service->stock($item->id, $v->id, 'adjust', '-10', null, 'Resolve stock');
        $this->service->active($item->id, $v->id, false);
        $this->assertFalse($v->fresh()->is_active);
        $this->assertSame($unit, $v->fresh()->stockUnit->id);
        $this->assertGreaterThan(0, InventoryTransaction::where('inventory_stock_unit_id', $unit)->count());
    }

    public function test_historical_rows_remain_unchanged_and_outstanding_issues_block_conversion(): void
    {
        $item = $this->product();
        $values = $this->choices($item);
        $v = $this->service->combination($item->id, [$values['Black']->id, $values['Small']->id], 'ONE');
        $id = DB::table('inventory_transactions')->insertGetId(['branch_id' => $item->branch_id, 'inventory_item_id' => $item->id, 'type' => 'receive', 'qty' => 8, 'created_at' => now(), 'updated_at' => now()]);
        $before = (array) DB::table('inventory_transactions')->find($id);
        $this->service->convert($item->id, [$v->id => '10'], '10');
        $this->assertSame($before, (array) DB::table('inventory_transactions')->find($id));
        $other = $this->product();
        $vals = $this->choices($other);
        $ov = $this->service->combination($other->id, [$vals['Black']->id, $vals['Small']->id], 'TWO');
        DB::table('inventory_transactions')->insert(['branch_id' => $other->branch_id, 'inventory_item_id' => $other->id, 'type' => 'issue', 'qty' => -1, 'created_at' => now(), 'updated_at' => now()]);
        $this->reject(fn () => $this->service->convert($other->id, [$ov->id => '10'], '10'));
    }

    public function test_item_manager_cannot_allocate_or_change_stock_without_independent_permission(): void
    {
        $item = $this->product();
        $values = $this->choices($item);
        $v = $this->service->combination($item->id, [$values['Black']->id, $values['Small']->id], 'PERMISSION');
        $user = \App\Models\User::factory()->create(['branch_id' => $this->branch->id]);
        $user->givePermissionTo(['inventory.view', 'inventory.items.manage']);
        $this->actingAs($user);
        $this->service->option($item->id, null, 'Material');
        $this->assertSame(3, $item->options()->count());
        try {
            $this->service->convert($item->id, [$v->id => '10'], '10');
            $this->fail('Allocation requires stock permission');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        try {
            $this->service->stock($item->id, $v->id, 'receive', '1', null, '');
            $this->fail('Receive requires stock permission');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertEquals(10, $item->stock->fresh()->qty_on_hand);
    }

    public function test_other_branch_cannot_manage_product(): void
    {
        $item = $this->product();
        $this->actingAsRole('sales', $this->otherBranch);
        $user = auth()->user();
        $user->givePermissionTo('inventory.items.manage');
        $this->reject(fn () => $this->service->option($item->id, null, 'Unauthorized'));
        $this->assertSame(0, $item->options()->count());
    }

    public function test_legacy_mapping_preserves_fabric_and_stock_unit_identity(): void
    {
        $item = $this->product();
        $v = $item->variants()->create(['name' => 'Legacy M', 'sku' => 'LEGACY-M', 'size' => 'Medium', 'color' => 'Black', 'price_delta' => 0, 'is_active' => true]);
        app(\App\Services\Inventory\StockUnitBackfillService::class)->run(false);
        $unit = $v->stockUnit->id;
        $fabric = \App\Models\Fabric::create(['name' => 'Fabric', 'code' => 'FAB']);
        $swatch = \App\Models\FabricVariant::create(['fabric_id' => $fabric->id, 'name' => 'Black', 'inventory_item_variant_id' => $v->id]);
        $values = $this->choices($item);
        $mapped = $this->service->combination($item->id, [$values['Black']->id, $values['Medium']->id], '', $v->id);
        $this->assertSame($v->id, $mapped->id);
        $this->assertSame($unit, $mapped->stockUnit->id);
        $this->assertSame($v->id, $swatch->fresh()->inventory_item_variant_id);
        $this->service->value($item->id, $values['Medium']->inventory_item_option_id, $values['Medium']->id, 'M');
        $this->assertSame('Black / M', $v->fresh()->display_name);
        $this->assertSame('LEGACY-M', $v->fresh()->sku);
    }

    public function test_livewire_combination_and_allocation_use_stable_form_keys(): void
    {
        $item = $this->product();
        $values = $this->choices($item);
        $component = \Livewire\Livewire::test(\App\Livewire\Inventory\Items\Variations::class, ['item' => $item->id]);
        $component->call('open', 'combination')->set('choices.option_'.$values['Black']->inventory_item_option_id, (string) $values['Black']->id)->set('choices.option_'.$values['Small']->inventory_item_option_id, (string) $values['Small']->id)->set('sku', 'FORM-ONLY')->call('save')->assertHasNoErrors();
        $v = $item->variants()->firstOrFail();
        $component->call('open', 'allocation')->set('allocations.variant_'.$v->id, '10')->call('save')->assertHasNoErrors();
        $this->assertSame('variants', $item->fresh()->variant_mode);
        $this->assertSame('10.00', $v->stockUnit->stock->qty_on_hand);
    }

    public function test_view_renders_workspace_and_livewire_option_flow(): void
    {
        $item = $this->product();
        $this->get(route('inventory.items.variations', $item->id))->assertOk()->assertSee('Simple product')->assertSee('Configure Variations');
        \Livewire\Livewire::test(\App\Livewire\Inventory\Items\Variations::class, ['item' => $item->id])->call('show', 'options')->call('open', 'option')->set('name', 'Material')->call('save')->assertHasNoErrors()->assertSee('Material');
    }
}

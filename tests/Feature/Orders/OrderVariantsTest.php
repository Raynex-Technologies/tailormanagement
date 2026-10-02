<?php

namespace Tests\Feature\Orders;

use App\Livewire\Orders\Form;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderPackageTemplate;
use App\Services\Inventory\VariantAdministrationService;
use App\Services\Orders\OrderCatalogCompositionService;
use App\Services\Orders\OrderInventoryPreflight;
use App\Services\Orders\OrderInventorySelectionService;
use App\Services\Orders\OrderInventorySynchronizationService;
use App\Services\Orders\OrderPackageInventoryService;
use Livewire\Livewire;
use Tests\TestCase;

class OrderVariantsTest extends TestCase
{
    private InventoryItem $product;

    private array $values = [];

    private array $variants = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsRole('superadmin', $this->branch);
        $s = app(VariantAdministrationService::class);
        $this->product = InventoryItem::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Plain T-Shirt', 'sku' => 'TSHIRT', 'default_sell_price' => 35000, 'is_active' => true]);
        $this->product->stock->update(['qty_on_hand' => 10, 'qty_reserved' => 0]);
        $color = $s->option($this->product->id, null, 'Color');
        $size = $s->option($this->product->id, null, 'Size', 1);
        foreach (['Black', 'White'] as $n) {
            $this->values[$n] = $s->value($this->product->id, $color->id, null, $n);
        }foreach (['Small', 'Medium', 'Large'] as $n) {
            $this->values[$n] = $s->value($this->product->id, $size->id, null, $n);
        }
        $alloc = [];
        foreach ([['Black', 'Small', 2, 35000], ['Black', 'Medium', 3, 35000], ['Black', 'Large', 1, 40000], ['White', 'Small', 1, 36000], ['White', 'Large', 3, 42000]] as [$c,$z,$q,$price]) {
            $v = $s->combination($this->product->id, [$this->values[$c]->id, $this->values[$z]->id], $c.'-'.$z);
            $s->identity($this->product->id, $v->id, $v->sku, (string) $price, '20000', 'SCAN-'.$v->id);
            $this->variants[$c.' '.$z] = $v;
            $alloc[$v->id] = (string) $q;
        }$s->convert($this->product->id, $alloc, '10');
    }

    private function data($unit, $qty = 1): array
    {
        return [...app(OrderInventorySelectionService::class)->snapshot($unit), 'qty' => $qty, 'unit_price' => $unit->selling_price, 'line_total' => $qty * $unit->selling_price];
    }

    private function rejects(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected rejection');
        } catch (\Illuminate\Validation\ValidationException|\DomainException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_preflight_groups_units_and_credits_only_owned_order(): void
    {
        $unit = $this->variants['Black Medium']->stockUnit;
        $preflight = app(OrderInventoryPreflight::class);
        $this->rejects(fn () => $preflight->validate([$this->data($unit, 2), $this->data($unit, 2)], null, $this->branch->id));
        $order = Order::factory()->create(['branch_id' => $this->branch->id, 'status' => \App\Enums\OrderStatus::New]);
        $line = $order->lines()->create($this->data($unit, 2));
        app(OrderInventorySynchronizationService::class)->synchronize($line, '2', auth()->user());
        $result = $preflight->validate([['id' => $line->id, ...$this->data($unit, 3)]], $order, $this->branch->id);
        $this->assertSame(3, $result[0]['qty']);
        $other = Order::factory()->create(['branch_id' => $this->branch->id, 'status' => \App\Enums\OrderStatus::New]);
        $this->rejects(fn () => $preflight->validate([['id' => $line->id, ...$this->data($unit, 3)]], $other, $this->branch->id));
        $this->rejects(fn () => $preflight->validate([$this->data($unit, 2)], $other, $this->branch->id));
        $this->rejects(fn () => $preflight->validate([['inventory_item_id' => $this->product->id, 'qty' => 1]], null, $this->branch->id));
        $unit->update(['allocation_status' => 'pending']);
        $this->rejects(fn () => $preflight->validate([$this->data($unit)], null, $this->branch->id));
    }

    public function test_order_selector_save_snapshot_and_noop(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $unit = $this->variants['Black Medium']->stockUnit;
        $form = Livewire::test(Form::class)->set('branch_id', $this->branch->id)->set('customer_id', $customer->id)
            ->call('addInventoryLine', $this->product->id)->assertSet('showInventoryVariationSelector', true)
            ->call('chooseInventoryOption', $this->values['White']->inventory_item_option_id, $this->values['White']->id)->assertSee('Not offered')
            ->call('chooseInventoryOption', $this->values['Black']->inventory_item_option_id, $this->values['Black']->id)
            ->call('chooseInventoryOption', $this->values['Medium']->inventory_item_option_id, $this->values['Medium']->id)
            ->call('confirmInventoryVariation')->assertSet('lines.0.inventory_stock_unit_id', $unit->id)->assertSet('lines.0.unit_price', 35000)
            ->call('save')->assertHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $line = $order->lines->first();
        $this->assertSame('Black / Medium', $line->variation_description);
        $this->assertSame($unit->id, $line->inventory_stock_unit_id);
        $count = \App\Models\InventoryTransaction::count();
        app(VariantAdministrationService::class)->value($this->product->id, $this->values['Black']->inventory_item_option_id, $this->values['Black']->id, 'Jet Black');
        Livewire::test(Form::class, ['order' => $order])->call('save')->assertHasNoErrors();
        $this->assertSame($count, \App\Models\InventoryTransaction::count());
        $this->assertSame('Black / Medium', $line->fresh()->variation_description);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Black / Medium');
    }

    public function test_package_split_exact_totals_prices_and_provenance(): void
    {
        $template = OrderPackageTemplate::create(['name' => 'Starter', 'available_all_branches' => false]);
        $template->branches()->attach($this->branch->id);
        $component = $template->items()->create(['inventory_item_id' => $this->product->id, 'variation_selection' => 'deferred', 'minimum_quantity' => 2, 'default_quantity' => 2, 'package_unit_price' => 12345]);
        $snapshot = $template->snapshot();
        $a = $this->variants['Black Medium']->stockUnit;
        $b = $this->variants['White Large']->stockUnit;
        $allocate = app(OrderPackageInventoryService::class);
        $key = 'component_'.$component->id;
        $this->rejects(fn () => $allocate->allocate($snapshot, [], $this->branch->id));
        $rows = [['inventory_stock_unit_id' => $a->id, 'quantity' => '1'], ['inventory_stock_unit_id' => $b->id, 'quantity' => '1']];
        $resolved = $allocate->allocate($snapshot, [$key => $rows], $this->branch->id);
        $lines = app(OrderCatalogCompositionService::class)->packageLines($resolved, 'instance-key');
        $this->assertCount(2, $lines);
        $this->assertSame('12345.00', $lines[0]['unit_price']);
        $this->assertSame($component->id, $lines[1]['order_package_template_item_id']);
        $this->assertSame('instance-key', $lines[1]['package_key']);
        $this->assertSame($b->id, $lines[1]['inventory_stock_unit_id']);
        $rows[1]['quantity'] = '2';
        $this->rejects(fn () => $allocate->allocate($snapshot, [$key => $rows], $this->branch->id));
        $rows[1]['quantity'] = '0';
        $this->rejects(fn () => $allocate->allocate($snapshot, [$key => $rows], $this->branch->id));
    }

    public function test_package_builder_fixed_and_deferred_then_order_split_save(): void
    {
        $a = $this->variants['Black Medium']->stockUnit;
        $b = $this->variants['White Large']->stockUnit;
        $builder = Livewire::test(\App\Livewire\OrderCatalog\PackageForm::class)
            ->set('name', 'Uniform Pack')->set('availableAllBranches', false)->set('branchIds', [$this->branch->id])
            ->call('addInventoryItem', $this->product->id)->assertSet('components.0.variation_selection', 'deferred')
            ->set('components.0.default_quantity', '2')->set('components.0.minimum_quantity', '2')->set('components.0.package_unit_price', '12345')
            ->call('save')->assertHasNoErrors();
        $template = OrderPackageTemplate::latest('id')->firstOrFail();
        $component = $template->items->first();
        $this->assertSame('deferred', $component->variation_selection);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $form = Livewire::test(Form::class)->set('branch_id', $this->branch->id)->set('customer_id', $customer->id)
            ->call('configurePackage', $template->id)->call('confirmPackageConfiguration')->assertHasErrors('packageQuantities')
            ->call('splitPackageQuantity', $component->id)
            ->assertSet('packageInventoryAllocations.component_'.$component->id.'.0.quantity', '1.00')
            ->assertSet('packageInventoryAllocations.component_'.$component->id.'.1.quantity', '1.00')
            ->set('packageInventoryAllocations.component_'.$component->id, [
                ['quantity' => '1', 'inventory_stock_unit_id' => $a->id], ['quantity' => '1', 'inventory_stock_unit_id' => $b->id]])
            ->call('confirmPackageConfiguration')->assertHasNoErrors()->call('save')->assertHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $this->assertCount(2, $order->lines);
        $this->assertSame('24690.00', $order->total);
        $this->assertTrue($order->lines->every(fn ($line) => $line->order_package_instance_id && $line->order_package_template_item_id === $component->id));
        Livewire::test(\App\Livewire\OrderCatalog\PackageForm::class, ['package' => $template])
            ->set('components.0.variation_selection', 'fixed')->call('chooseFixedVariation', 0)
            ->call('chooseInventoryOption', $this->values['White']->inventory_item_option_id, $this->values['White']->id)
            ->call('chooseInventoryOption', $this->values['Large']->inventory_item_option_id, $this->values['Large']->id)
            ->call('confirmInventoryVariation')->call('save')->assertHasNoErrors();
        $this->assertSame($b->id, $component->fresh()->inventory_stock_unit_id);
        $this->assertSame('fixed', $component->fresh()->variation_selection);
        $b->update(['is_active' => false]);
        $movementCount = \App\Models\InventoryTransaction::count();
        Livewire::test(Form::class, ['order' => $order])->call('save')->assertHasNoErrors();
        $this->assertSame($movementCount, \App\Models\InventoryTransaction::count());
        Livewire::test(\App\Livewire\OrderCatalog\PackageForm::class, ['package' => $template->fresh()])->assertSee('Requires attention');
    }

    public function test_failed_replacement_rolls_back_original_return(): void
    {
        $a = $this->variants['Black Medium']->stockUnit;
        $b = $this->variants['Black Large']->stockUnit;
        $order = Order::factory()->create(['branch_id' => $this->branch->id, 'status' => \App\Enums\OrderStatus::New]);
        $line = $order->lines()->create($this->data($a, 2));
        $sync = app(OrderInventorySynchronizationService::class);
        $sync->synchronize($line, '2', auth()->user());
        $line->update($this->data($b, 2));
        $before = \App\Models\InventoryTransaction::count();
        $this->rejects(fn () => $sync->synchronize($line, '2', auth()->user()));
        $this->assertSame('1.00', $a->stock->fresh()->qty_on_hand);
        $this->assertSame('1.00', $b->stock->fresh()->qty_on_hand);
        $this->assertSame($before, \App\Models\InventoryTransaction::count());
    }

    public function test_order_only_permission_and_legacy_snapshot_preservation(): void
    {
        $foreign = InventoryItem::factory()->create(['branch_id' => $this->otherBranch->id]);
        $actor = \App\Models\User::factory()->create(['branch_id' => $this->branch->id]);
        $actor->givePermissionTo(['orders.create', 'orders.update', 'orders.view']);
        $this->actingAs($actor);
        Livewire::test(Form::class)->call('addInventoryLine', $this->product->id)->assertSet('showInventoryVariationSelector', true);
        $this->assertFalse($actor->can('inventory.items.manage'));
        $this->assertFalse($actor->can('inventory.stock.adjust'));
        Livewire::test(\App\Livewire\OrderCatalog\PackageForm::class)->assertForbidden();
        $order = Order::factory()->create(['branch_id' => $this->branch->id, 'status' => \App\Enums\OrderStatus::New]);
        $line = $order->lines()->create(['inventory_item_id' => $this->product->id, 'item_name' => 'Historical shirt', 'sku' => 'OLD', 'qty' => 1, 'unit_price' => 50, 'line_total' => 50]);
        $result = app(OrderInventoryPreflight::class)->validate([['id' => $line->id, 'inventory_item_id' => $this->product->id, 'qty' => 1, 'item_name' => 'Forged label']], $order, $this->branch->id);
        $this->assertSame('Historical shirt', $result[0]['item_name']);
        $this->assertNull($line->inventory_stock_unit_id);
        $this->rejects(fn () => app(OrderInventoryPreflight::class)->validate([['inventory_item_id' => $foreign->id, 'inventory_stock_unit_id' => $foreign->simpleStockUnit->id, 'qty' => 1]], null, $this->branch->id));
    }

    public function test_legacy_simple_line_keeps_null_identity_and_does_not_churn_movements(): void
    {
        $item = InventoryItem::factory()->create(['branch_id' => $this->branch->id]);
        $item->stock->update(['qty_on_hand' => 10, 'qty_reserved' => 0]);
        $order = Order::factory()->create(['branch_id' => $this->branch->id, 'status' => \App\Enums\OrderStatus::New]);
        $line = $order->lines()->create(['inventory_item_id' => $item->id, 'item_name' => 'Legacy', 'qty' => 2, 'unit_price' => 100, 'line_total' => 200]);
        \Illuminate\Support\Facades\DB::table('order_lines')->where('id', $line->id)->update(['inventory_stock_unit_id' => null]);
        $line->refresh();
        $sync = app(OrderInventorySynchronizationService::class);
        $sync->synchronize($line, '2', auth()->user());
        $count = \App\Models\InventoryTransaction::count();
        $sync->synchronize($line, '2', auth()->user());
        $this->assertSame($count, \App\Models\InventoryTransaction::count());
        $this->assertNull($line->fresh()->inventory_stock_unit_id);
        $this->assertSame('8.00', $item->stock->fresh()->qty_on_hand);
    }
}

<?php

namespace Tests\Feature\OrderCatalog;

use App\Enums\OrderCatalogItemType;
use App\Enums\OrderCatalogQuantityBehavior;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Orders\Form as OrderForm;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderCatalogItem;
use App\Models\OrderLine;
use App\Models\OrderPackageTemplate;
use App\Models\OrderPayment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCatalogOrderCompositionTest extends TestCase
{
    public function test_order_contents_preserves_custom_orders_and_exposes_one_catalog_entry_surface(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        Livewire::test(OrderForm::class)
            ->assertSee('Order Contents')->assertSee('Add from Catalog')->assertSee('Add Custom Item')
            ->assertDontSee('Add Inventory')
            ->set('customer_id', $customer->id)
            ->call('addLine')
            ->set('lines.0.item_name', 'Bespoke custom jacket')
            ->set('lines.0.qty', 1)->set('lines.0.unit_price', 125000)
            ->call('save')->assertHasNoErrors();

        $line = Order::query()->latest('id')->firstOrFail()->lines()->firstOrFail();
        $this->assertSame('Bespoke custom jacket', $line->item_name);
        $this->assertNull($line->order_catalog_item_id);
        $this->assertNull($line->order_package_instance_id);
    }

    public function test_direct_garment_expands_units_and_persists_independent_measurements(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $garment = $this->catalogItem();

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->call('configureDirectCatalogItem', $garment->id)
            ->set('directCatalogQuantity', '2')->call('confirmDirectCatalogItem')
            ->assertSet('lines.0.item_name', 'Two-Piece Suit #1')->assertSet('lines.0.qty', '1')
            ->assertSet('lines.1.item_name', 'Two-Piece Suit #2')->assertSet('lines.1.qty', '1')
            ->set('lines.0.measurements', [['key' => 'Chest', 'value' => '40']])
            ->set('lines.1.measurements', [['key' => 'Chest', 'value' => '42']])
            ->call('save')->assertHasNoErrors();

        $order = Order::query()->with('lines.measurement')->latest('id')->firstOrFail();
        $this->assertCount(2, $order->lines);
        $this->assertTrue($order->lines->every(fn (OrderLine $line) => $line->order_catalog_item_id === $garment->id));
        $this->assertSame('40', $order->lines[0]->measurement->measurements['Chest']);
        $this->assertSame('42', $order->lines[1]->measurement->measurements['Chest']);
        $this->assertSame('1000000.00', (string) $order->subtotal);
    }

    public function test_direct_service_is_bulk_and_inventory_uses_existing_stock_flow(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $service = $this->catalogItem([
            'name' => 'Express Alteration', 'type' => OrderCatalogItemType::Service,
            'quantity_behavior' => OrderCatalogQuantityBehavior::Bulk,
            'requires_measurements' => false, 'default_selling_price' => '25000.00',
        ]);
        $inventory = $this->inventoryItem();

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->call('configureDirectCatalogItem', $service->id)
            ->set('directCatalogQuantity', '3')->call('confirmDirectCatalogItem')
            ->call('toggleCatalogPicker')->assertSee('Inventory Products')
            ->call('addInventoryLine', $inventory->id)->set('lines.1.qty', 2)
            ->call('save')->assertHasNoErrors();

        $order = Order::query()->with('lines')->latest('id')->firstOrFail();
        $serviceLine = $order->lines->firstWhere('order_catalog_item_id', $service->id);
        $inventoryLine = $order->lines->firstWhere('inventory_item_id', $inventory->id);
        $this->assertSame('3.00', $serviceLine->qty);
        $this->assertSame('75000.00', $serviceLine->line_total);
        $this->assertSame('2.00', $inventoryLine->qty);
        $this->assertSame('8.00', (string) $inventory->stock()->firstOrFail()->qty_on_hand);
    }

    public function test_mixed_package_persists_snapshots_and_is_not_double_counted(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        [$package, $garment, $service, $inventory] = $this->mixedPackage();
        $inventoryComponent = $package->items->firstWhere('inventory_item_id', $inventory->id);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)->call('configurePackage', $package->id)
            ->set('packageQuantities.'.$inventoryComponent->id, '1')
            ->call('confirmPackageConfiguration')->assertSet('total', 950000.0)
            ->call('addLine')->set('lines.4.item_name', 'Custom shirt')
            ->set('lines.4.qty', 1)->set('lines.4.unit_price', 120000)
            ->call('save')->assertHasNoErrors();

        $order = Order::query()->with(['lines', 'packageInstances'])->latest('id')->firstOrFail();
        $instance = $order->packageInstances->sole();
        $this->assertCount(5, $order->lines);
        $this->assertCount(2, $order->lines->where('order_catalog_item_id', $garment->id));
        $this->assertSame('2.00', $order->lines->firstWhere('order_catalog_item_id', $service->id)->qty);
        $this->assertSame('1.00', $order->lines->firstWhere('inventory_item_id', $inventory->id)->qty);
        $this->assertSame('970000.00', (string) $instance->original_package_total);
        $this->assertSame('950000.00', (string) $instance->configured_package_total);
        $this->assertSame('2', $instance->original_component_snapshot[2]['configured_quantity']);
        $this->assertSame('1', $instance->configured_component_snapshot[2]['configured_quantity']);
        $this->assertSame($package->revision, $instance->source_template_revision);
        $this->assertSame('1070000.00', (string) $order->subtotal);
        $this->assertTrue($order->lines->whereNotNull('order_package_instance_id')->every(
            fn (OrderLine $line) => $line->order_package_instance_id === $instance->id
        ));
    }

    public function test_package_configuration_rejects_minimum_and_fractional_individual_quantities(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        [$package, $garment] = $this->mixedPackage();
        $garmentComponent = $package->items->firstWhere('order_catalog_item_id', $garment->id);

        Livewire::test(OrderForm::class)
            ->call('configurePackage', $package->id)
            ->set('packageQuantities.'.$garmentComponent->id, '0')
            ->call('confirmPackageConfiguration')->assertHasErrors('packageQuantities')
            ->set('packageQuantities.'.$garmentComponent->id, '1.5')
            ->call('confirmPackageConfiguration')->assertHasErrors('packageQuantities');
    }

    public function test_package_edit_preserves_unaffected_state_and_removal_reconciles_stock(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        [$package, $garment, , $inventory] = $this->mixedPackage();

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)->call('configurePackage', $package->id)
            ->call('confirmPackageConfiguration')
            ->set('lines.0.measurements', [['key' => 'Chest', 'value' => '40']])
            ->set('lines.1.measurements', [['key' => 'Chest', 'value' => '42']])
            ->call('save')->assertHasNoErrors();

        $order = Order::query()->with('lines.measurement')->latest('id')->firstOrFail();
        $keptLine = $order->lines->firstWhere('item_name', 'Two-Piece Suit #1');
        $removedLine = $order->lines->firstWhere('item_name', 'Two-Piece Suit #2');
        $garmentComponent = $package->items->firstWhere('order_catalog_item_id', $garment->id);
        $edit = Livewire::test(OrderForm::class, ['order' => $order]);
        $packageKey = array_key_first($edit->get('packages'));

        $edit->call('customizePackage', $packageKey)
            ->set('packageQuantities.'.$garmentComponent->id, '1')
            ->call('confirmPackageConfiguration')->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('order_lines', ['id' => $keptLine->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('order_lines', ['id' => $removedLine->id]);
        $this->assertSame('40', $keptLine->measurement()->firstOrFail()->measurements['Chest']);
        $this->assertSame('8.00', (string) $inventory->stock()->firstOrFail()->qty_on_hand);

        $remove = Livewire::test(OrderForm::class, ['order' => $order->fresh()]);
        $removeKey = array_key_first($remove->get('packages'));
        $remove->call('removePackage', $removeKey)
            ->call('addLine')
            ->set('lines.0.item_name', 'Replacement custom line')
            ->set('lines.0.qty', 1)->set('lines.0.unit_price', 1000)
            ->call('save')->assertHasNoErrors();

        $this->assertDatabaseMissing('order_package_instances', ['order_id' => $order->id]);
        $this->assertSame('10.00', (string) $inventory->stock()->firstOrFail()->qty_on_hand);
    }

    public function test_branch_scoping_and_submitted_ids_cannot_bypass_rules(): void
    {
        $this->actingAsRole('admin');
        [$package] = $this->mixedPackage();
        $allowed = $this->catalogItem(['name' => 'Allowed global garment']);
        $restricted = $this->catalogItem(['name' => 'Other branch garment', 'available_all_branches' => false]);
        $restricted->branches()->sync([$this->otherBranch->id]);

        Livewire::test(OrderForm::class)
            ->set('branch_id', $this->branch->id)->call('configurePackage', $package->id)
            ->call('confirmPackageConfiguration')->set('branch_id', $this->otherBranch->id)
            ->assertSet('branch_id', $this->branch->id)->assertHasErrors('branch_id');

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        Livewire::test(OrderForm::class)
            ->set('branch_id', $this->branch->id)->set('customer_id', $customer->id)
            ->call('configureDirectCatalogItem', $allowed->id)->call('confirmDirectCatalogItem')
            ->set('lines.0.order_catalog_item_id', $restricted->id)
            ->call('save')->assertHasErrors('lines');
    }

    public function test_archived_package_is_unavailable_for_selection(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        [$package] = $this->mixedPackage();
        $package->archive();

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(OrderForm::class)->call('configurePackage', $package->id);
    }

    public function test_archived_historical_package_and_legacy_lines_still_edit_from_snapshots(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        [$package] = $this->mixedPackage();

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)->call('configurePackage', $package->id)
            ->call('confirmPackageConfiguration')->call('save')->assertHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $instance = $order->packageInstances()->firstOrFail();
        $package->update(['name' => 'Changed template name', 'revision' => 9, 'archived_at' => now()]);
        $legacyLine = $order->lines()->create([
            'item_name' => 'Historical custom line', 'qty' => 1,
            'unit_price' => 1000, 'line_total' => 1000,
        ]);
        $order->recalculateTotals();

        $edit = Livewire::test(OrderForm::class, ['order' => $order->fresh()]);
        $historicalPackages = $edit->get('packages');
        $loadedLines = $edit->get('lines');
        $this->assertCount(1, $historicalPackages);
        $this->assertSame('Premium Wedding Package', reset($historicalPackages)['original_snapshot']['name']);
        $this->assertTrue(collect($loadedLines)->contains(fn (array $line) => $line['item_name'] === 'Historical custom line'));
        $edit->set('notes', 'Historical edit remains supported')->call('save')->assertHasNoErrors();

        $this->assertSame('Premium Wedding Package', $instance->fresh()->package_name);
        $this->assertSame(1, $instance->fresh()->source_template_revision);
        $this->assertDatabaseHas('order_lines', ['id' => $legacyLine->id, 'deleted_at' => null]);
    }

    public function test_invoice_editor_rejects_package_line_changes_despite_flag_tampering(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        [$package] = $this->mixedPackage();

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)->call('configurePackage', $package->id)
            ->call('confirmPackageConfiguration')->call('save')->assertHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $invoice = Invoice::syncFromOrder($order->fresh(['lines']), $user->id);
        $originalQty = $order->lines()->firstOrFail()->qty;

        Livewire::test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('startEditing')->set('lines.0.is_package_linked', false)
            ->set('lines.0.qty', 9)->call('save')->assertHasErrors('lines');

        $this->assertSame($originalQty, $order->lines()->firstOrFail()->fresh()->qty);
    }

    public function test_payment_floor_and_status_reconciliation(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $order = $this->ordinaryOrder($customer, $user->id, '1000.00');
        OrderPayment::create([
            'branch_id' => $this->branch->id, 'order_id' => $order->id,
            'amount' => '900.00', 'paid_at' => now(), 'received_by' => $user->id,
        ]);
        $order->update(['payment_status' => PaymentStatus::Partial]);

        Livewire::test(OrderForm::class, ['order' => $order])
            ->set('lines.0.unit_price', 850)->call('save')->assertHasErrors('total');
        $this->assertSame('1000.00', (string) $order->fresh()->total);

        Livewire::test(OrderForm::class, ['order' => $order->fresh()])
            ->set('lines.0.unit_price', 900)->call('save')->assertHasNoErrors();

        $this->assertSame('900.00', (string) $order->fresh()->total);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    private function catalogItem(array $attributes = []): OrderCatalogItem
    {
        return OrderCatalogItem::create([
            'name' => 'Two-Piece Suit',
            'type' => OrderCatalogItemType::Garment,
            'default_selling_price' => '500000.00',
            'requires_measurements' => true,
            'quantity_behavior' => OrderCatalogQuantityBehavior::Individual,
            'available_all_branches' => true,
            ...$attributes,
        ]);
    }

    private function inventoryItem(array $attributes = []): InventoryItem
    {
        $inventory = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Silk Tie',
            'default_sell_price' => '40000.00',
            'is_active' => true,
            ...$attributes,
        ]);
        $inventory->stock()->update(['qty_on_hand' => 10, 'qty_reserved' => 0]);

        return $inventory;
    }

    private function mixedPackage(): array
    {
        $garment = $this->catalogItem();
        $service = $this->catalogItem([
            'name' => 'Pressing Service',
            'type' => OrderCatalogItemType::Service,
            'default_selling_price' => '20000.00',
            'requires_measurements' => false,
            'quantity_behavior' => OrderCatalogQuantityBehavior::Bulk,
        ]);
        $inventory = $this->inventoryItem(['name' => 'Wedding Socks']);
        $package = OrderPackageTemplate::create([
            'name' => 'Premium Wedding Package',
            'description' => 'A complete wedding set.',
            'available_all_branches' => false,
        ]);
        $package->branches()->sync([$this->branch->id]);
        $package->items()->create([
            'order_catalog_item_id' => $garment->id,
            'minimum_quantity' => '1', 'default_quantity' => '2', 'maximum_quantity' => '3',
            'package_unit_price' => '450000', 'sort_order' => 1,
        ]);
        $package->items()->create([
            'order_catalog_item_id' => $service->id,
            'minimum_quantity' => '1', 'default_quantity' => '2', 'maximum_quantity' => '4',
            'package_unit_price' => '15000', 'sort_order' => 2,
        ]);
        $package->items()->create([
            'inventory_item_id' => $inventory->id,
            'minimum_quantity' => '0', 'default_quantity' => '2', 'maximum_quantity' => '3',
            'package_unit_price' => '20000', 'sort_order' => 3,
        ]);

        return [$package->fresh(['branches', 'items.catalogItem', 'items.inventoryItem']), $garment, $service, $inventory];
    }

    private function ordinaryOrder(Customer $customer, int $userId, string $total): Order
    {
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $userId,
        ]);
        $order->lines()->create([
            'item_name' => 'Legacy line', 'qty' => 1,
            'unit_price' => $total, 'line_total' => $total,
        ]);

        return $order;
    }
}

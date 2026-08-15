<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Orders\Form as OrderForm;
use App\Livewire\Orders\Show as OrderShow;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderExpense;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    public function test_order_show_record_payment_panel_requires_create_not_view_permission(): void
    {
        PaymentMethod::query()->updateOrCreate(
            ['id' => 1],
            ['name' => 'Default']
        );

        $role = Role::create(['name' => 'payment_creator_only', 'guard_name' => 'web']);
        $role->givePermissionTo(['orders.view', 'orders.view_financials', 'payments.create']);

        $user = $this->createUserWithRole('payment_creator_only', $this->branch);
        $this->actingAs($user);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'due_date' => now()->addDays(7),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        Livewire::test(OrderShow::class, ['order' => $order])
            ->assertSee('Record Payment')
            ->assertDontSee('Payment History');
    }

    public function test_order_creation_writes_totals_correctly(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        // Create order without factory auto-lines
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'due_date' => now()->addDays(7),
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        // Add order lines
        OrderLine::create([
            'order_id' => $order->id,
            'item_name' => 'Test Suit',
            'qty' => 2,
            'unit_price' => 50000,
            'line_total' => 100000,
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'item_name' => 'Test Shirt',
            'qty' => 3,
            'unit_price' => 20000,
            'line_total' => 60000,
        ]);

        // Recalculate totals
        $order->recalculateTotals();
        $order->refresh();

        $this->assertEquals(160000, $order->subtotal);
        $this->assertEquals(160000, $order->total);
    }

    public function test_order_creation_sets_branch_id(): void
    {
        $user = $this->actingAsRole('sales', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'due_date' => now()->addDays(7),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $this->assertEquals($this->branch->id, $order->branch_id);
    }

    public function test_new_order_with_deposit_sends_only_order_created_sms_with_deposit(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'phone' => '0712345678',
        ]);
        $paymentMethod = PaymentMethod::query()->firstOrCreate(
            ['name' => 'Cash'],
            ['is_enabled' => true]
        );

        SmsTemplate::instance()->update([
            'templates' => array_replace(SmsTemplate::defaultTemplates(), [
                'order_created' => 'Order {order_number}, deposit {deposit}.',
            ]),
        ]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->set('lines.0.item_name', 'Suit')
            ->set('lines.0.qty', 1)
            ->set('lines.0.unit_price', 90000)
            ->set('deposit_amount', 30000)
            ->set('deposit_payment_method_id', $paymentMethod->id)
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $logs = SmsLog::query()
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->get();

        $this->assertCount(1, $logs);
        $this->assertSame('order_created', $logs->first()->template_code);
        $this->assertStringContainsString('deposit Tsh 30,000', $logs->first()->message);
    }

    public function test_order_line_totals_recalculate_live_before_save(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        Livewire::test(OrderForm::class)
            ->set('lines.0.qty', 2)
            ->set('lines.0.unit_price', 2500)
            ->assertSet('lines.0.line_total', 5000.0)
            ->assertSet('subtotal', 5000.0)
            ->assertSet('total', 5000.0)
            ->set('discount', 500)
            ->assertSet('total', 4500.0);
    }

    public function test_create_order_can_attach_inventory_item_with_custom_price_and_decrease_stock(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Premium Lining',
            'default_sell_price' => 5000,
        ]);
        $item->stock()->update(['qty_on_hand' => 10, 'qty_reserved' => 0]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->call('addInventoryLine', $item->id)
            ->set('lines.0.qty', 2)
            ->set('lines.0.unit_price', 6500)
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->with('lines')->latest('id')->first();

        $this->assertNotNull($order);
        $this->assertEquals(13000.0, (float) $order->subtotal);
        $this->assertEquals(13000.0, (float) $order->total);

        $line = $order->lines->first();
        $this->assertSame($item->id, (int) $line->inventory_item_id);
        $this->assertSame('Premium Lining', $line->item_name);
        $this->assertEquals(6500.0, (float) $line->unit_price);
        $this->assertEquals(2.0, (float) $line->qty);

        $this->assertEquals(8.0, (float) $item->stock()->first()->qty_on_hand);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'reference_type' => OrderLine::class,
            'reference_id' => $line->id,
            'type' => 'issue',
            'qty' => -2,
        ]);
    }

    public function test_edit_order_inventory_quantity_resyncs_stock_without_over_returning(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $item = InventoryItem::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Cotton Roll',
            'default_sell_price' => 3000,
        ]);
        $item->stock()->update(['qty_on_hand' => 10, 'qty_reserved' => 0]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->call('addInventoryLine', $item->id)
            ->set('lines.0.qty', 2)
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->with('lines')->latest('id')->first();
        $this->assertEquals(8.0, (float) $item->stock()->first()->qty_on_hand);

        Livewire::test(OrderForm::class, ['order' => $order])
            ->set('lines.0.qty', 4)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(6.0, (float) $item->stock()->first()->qty_on_hand);

        $line = $order->lines()->first();
        $netQty = InventoryTransaction::query()
            ->where('reference_type', OrderLine::class)
            ->where('reference_id', $line->id)
            ->sum('qty');

        $this->assertEquals(-4.0, (float) $netQty);
    }

    public function test_status_transitions_enforce_forward_only_rules(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'due_date' => now()->addDays(7),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        // Valid: New -> InProgress
        $this->assertTrue($order->canTransitionTo(OrderStatus::InProgress));

        // Invalid: New -> Ready (skipping InProgress)
        $this->assertFalse($order->canTransitionTo(OrderStatus::Ready));

        // Invalid: New -> Completed (skipping multiple steps)
        $this->assertFalse($order->canTransitionTo(OrderStatus::Completed));

        // Valid: New -> Cancelled (cancel always allowed)
        $this->assertTrue($order->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_status_transition_in_progress_to_ready_allowed(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress,
            'due_date' => now()->addDays(7),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $this->assertTrue($order->canTransitionTo(OrderStatus::Ready));
        $this->assertFalse($order->canTransitionTo(OrderStatus::New)); // Cannot go back
    }

    public function test_ready_order_cannot_transition_to_delivered_when_balance_exists(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::Ready,
            'due_date' => now()->addDays(7),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Partial,
            'created_by' => $user->id,
        ]);

        $this->assertTrue($order->hasOutstandingBalance());
        $this->assertFalse($order->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_delivered_order_with_balance_cannot_create_delivery_note(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'due_date' => now()->addDays(7),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Partial,
            'created_by' => $user->id,
        ]);

        $this->assertTrue($order->hasOutstandingBalance());
        $this->assertFalse($order->canCreateDeliveryNote());
    }

    public function test_delivery_note_creation_only_when_allowed(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        // Order in "New" status - cannot create delivery note
        $newOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'due_date' => now()->addDays(7),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $this->assertFalse($newOrder->canCreateDeliveryNote());

        // Order in "Delivered" status - can create delivery note
        $deliveredOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'due_date' => now()->addDays(7),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Paid,
            'created_by' => $user->id,
        ]);

        OrderPayment::create([
            'branch_id' => $this->branch->id,
            'order_id' => $deliveredOrder->id,
            'amount' => 50000,
            'paid_at' => now(),
            'received_by' => $user->id,
            'payment_method_id' => null,
        ]);

        $this->assertTrue($deliveredOrder->canCreateDeliveryNote());
    }

    public function test_cannot_create_duplicate_delivery_note(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'due_date' => now()->addDays(7),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Paid,
            'created_by' => $user->id,
        ]);

        OrderPayment::create([
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'amount' => 50000,
            'paid_at' => now(),
            'received_by' => $user->id,
            'payment_method_id' => null,
        ]);

        // Create first delivery note
        $order->deliveryNote()->create([
            'branch_id' => $this->branch->id,
            'delivery_note_no' => 'DN-TEST-001',
            'delivered_at' => now(),
            'delivered_by' => $user->id,
        ]);

        // Should not be able to create another
        $this->assertFalse($order->canCreateDeliveryNote());
    }

    public function test_completed_at_is_set_when_status_changes_to_completed(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'due_date' => now()->addDays(7),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Paid,
            'created_by' => $user->id,
        ]);

        $this->assertNull($order->completed_at);

        $order->update(['status' => OrderStatus::Completed]);
        $order->refresh();

        $this->assertNotNull($order->completed_at);
    }

    public function test_create_order_expenses_are_collapsed_to_one_row_per_selected_order_tailor(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->set('assigned_tailor_id', $tailor->id)
            ->set('lines.0.item_name', 'Three-piece suit')
            ->set('lines.0.qty', 1)
            ->set('lines.0.unit_price', 150000)
            ->set('order_expenses', [
                ['notes' => 'Labour Charge', 'amount' => 25000],
                ['notes' => 'Other', 'amount' => 8000],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);

        $expenses = OrderExpense::query()
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(1, $expenses);
        $this->assertSame($tailor->id, $expenses[0]->tailor_id);
        $this->assertSame('Labour Charge', $expenses[0]->notes);
        $this->assertEquals(25000.0, (float) $expenses[0]->amount);
    }

    public function test_create_order_expenses_use_single_line_tailor_when_order_tailor_is_empty(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->set('lines', [
                [
                    'id' => null,
                    'assigned_tailor_id' => $tailor->id,
                    'item_name' => 'Kanzu',
                    'qty' => 1,
                    'unit_price' => 70000,
                    'line_total' => 70000,
                    'notes' => '',
                    'measurements' => [['key' => '', 'value' => '']],
                ],
            ])
            ->set('order_expenses', [
                ['notes' => 'Additional Materials', 'amount' => 5000],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);

        $expense = OrderExpense::query()
            ->where('order_id', $order->id)
            ->first();

        $this->assertNotNull($expense);
        $this->assertSame($tailor->id, $expense->tailor_id);
        $this->assertSame('Additional Materials', $expense->notes);
    }

    public function test_create_order_expenses_do_not_fail_when_order_tailor_value_is_zero_and_line_tailor_is_selected(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->set('assigned_tailor_id', 0)
            ->set('lines.0.item_name', 'Blazer')
            ->set('lines.0.qty', 1)
            ->set('lines.0.unit_price', 85000)
            ->set('lines.0.assigned_tailor_id', (string) $tailor->id)
            ->set('order_expenses', [
                ['notes' => 'Additional Materials', 'amount' => 7000],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);

        $expense = OrderExpense::query()
            ->where('order_id', $order->id)
            ->first();

        $this->assertNotNull($expense);
        $this->assertSame($tailor->id, $expense->tailor_id);
    }

    public function test_create_order_expenses_create_one_row_per_inline_tailor_when_order_tailor_is_empty(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $tailorA = $this->createUserWithRole('tailor', $this->branch);
        $tailorB = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->set('lines', [
                [
                    'id' => null,
                    'assigned_tailor_id' => $tailorA->id,
                    'item_name' => 'Shirt',
                    'qty' => 1,
                    'unit_price' => 40000,
                    'line_total' => 40000,
                    'notes' => '',
                    'measurements' => [['key' => '', 'value' => '']],
                ],
                [
                    'id' => null,
                    'assigned_tailor_id' => $tailorB->id,
                    'item_name' => 'Trouser',
                    'qty' => 1,
                    'unit_price' => 45000,
                    'line_total' => 45000,
                    'notes' => '',
                    'measurements' => [['key' => '', 'value' => '']],
                ],
            ])
            ->set('order_expenses', [
                ['notes' => 'Labour Charge', 'amount' => 5000],
                ['notes' => 'Labour Charge', 'amount' => 4000],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);

        $expenses = OrderExpense::query()
            ->where('order_id', $order->id)
            ->orderBy('tailor_id')
            ->get();

        $this->assertCount(2, $expenses);
        $this->assertSame([$tailorA->id, $tailorB->id], $expenses->pluck('tailor_id')->all());
    }

    public function test_create_order_expenses_dedupe_inline_tailor_when_multiple_lines_share_same_tailor(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->set('lines', [
                [
                    'id' => null,
                    'assigned_tailor_id' => $tailor->id,
                    'item_name' => 'Shirt',
                    'qty' => 1,
                    'unit_price' => 40000,
                    'line_total' => 40000,
                    'notes' => '',
                    'measurements' => [['key' => '', 'value' => '']],
                ],
                [
                    'id' => null,
                    'assigned_tailor_id' => $tailor->id,
                    'item_name' => 'Trouser',
                    'qty' => 1,
                    'unit_price' => 45000,
                    'line_total' => 45000,
                    'notes' => '',
                    'measurements' => [['key' => '', 'value' => '']],
                ],
            ])
            ->set('order_expenses', [
                ['notes' => 'Labour Charge', 'amount' => 12000],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);

        $expenses = OrderExpense::query()
            ->where('order_id', $order->id)
            ->get();

        $this->assertCount(1, $expenses);
        $this->assertSame($tailor->id, (int) $expenses->first()->tailor_id);
    }

    public function test_create_order_new_customer_phone_must_be_unique_within_branch(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'phone' => '+255700888111',
        ]);

        Livewire::test(OrderForm::class)
            ->call('toggleNewCustomerForm')
            ->set('newCustomerName', 'Order Customer')
            ->set('newCustomerPhone', '+255700888111')
            ->set('lines.0.item_name', 'Wedding Suit')
            ->set('lines.0.qty', 1)
            ->set('lines.0.unit_price', 220000)
            ->call('save')
            ->assertHasErrors(['newCustomerPhone' => 'unique']);
    }

    public function test_order_scopes_include_line_level_tailor_assignments(): void
    {
        $manager = $this->actingAsRole('branch_manager', $this->branch);
        $tailorA = $this->createUserWithRole('tailor', $this->branch);
        $tailorB = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $lineAssignedOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $manager->id,
        ]);

        OrderLine::create([
            'order_id' => $lineAssignedOrder->id,
            'assigned_tailor_id' => $tailorA->id,
            'item_name' => 'Shirt',
            'qty' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);

        $orderAssignedOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'assigned_tailor_id' => $tailorA->id,
            'status' => OrderStatus::New,
            'subtotal' => 60000,
            'discount' => 0,
            'total' => 60000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $manager->id,
        ]);

        OrderLine::create([
            'order_id' => $orderAssignedOrder->id,
            'assigned_tailor_id' => $tailorB->id,
            'item_name' => 'Trouser',
            'qty' => 1,
            'unit_price' => 60000,
            'line_total' => 60000,
        ]);

        $otherOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'assigned_tailor_id' => $tailorB->id,
            'status' => OrderStatus::New,
            'subtotal' => 70000,
            'discount' => 0,
            'total' => 70000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $manager->id,
        ]);

        OrderLine::create([
            'order_id' => $otherOrder->id,
            'assigned_tailor_id' => $tailorB->id,
            'item_name' => 'Jacket',
            'qty' => 1,
            'unit_price' => 70000,
            'line_total' => 70000,
        ]);

        $assignedOrderIds = Order::query()
            ->assignedTo($tailorA->id)
            ->pluck('id')
            ->all();

        $forTailorOrderIds = Order::query()
            ->forTailor($tailorA->id)
            ->pluck('id')
            ->all();

        $this->assertContains($lineAssignedOrder->id, $assignedOrderIds);
        $this->assertContains($orderAssignedOrder->id, $assignedOrderIds);
        $this->assertNotContains($otherOrder->id, $assignedOrderIds);

        $this->assertContains($lineAssignedOrder->id, $forTailorOrderIds);
        $this->assertContains($orderAssignedOrder->id, $forTailorOrderIds);
        $this->assertNotContains($otherOrder->id, $forTailorOrderIds);
    }

    public function test_order_collects_unique_involved_tailor_names_from_order_and_line_assignments(): void
    {
        $manager = $this->actingAsRole('branch_manager', $this->branch);
        $tailorA = $this->createUserWithRole('tailor', $this->branch);
        $tailorB = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'assigned_tailor_id' => $tailorA->id,
            'status' => OrderStatus::New,
            'subtotal' => 95000,
            'discount' => 0,
            'total' => 95000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $manager->id,
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'assigned_tailor_id' => $tailorA->id,
            'item_name' => 'Suit',
            'qty' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'assigned_tailor_id' => $tailorB->id,
            'item_name' => 'Shirt',
            'qty' => 1,
            'unit_price' => 45000,
            'line_total' => 45000,
        ]);

        $order->load(['assignedTailor', 'lines.assignedTailor']);

        $this->assertSame(
            [$tailorA->name, $tailorB->name],
            $order->involvedTailorNames()->all()
        );
        $this->assertTrue($order->hasPerItemTailorAssignments());
        $this->assertTrue($order->hasTailorAssignments());
    }

    public function test_order_show_hides_assign_tailor_button_when_line_tailors_already_exist(): void
    {
        $manager = $this->actingAsRole('branch_manager', $this->branch);
        $tailorA = $this->createUserWithRole('tailor', $this->branch);
        $tailorB = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'subtotal' => 95000,
            'discount' => 0,
            'total' => 95000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $manager->id,
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'assigned_tailor_id' => $tailorA->id,
            'item_name' => 'Suit',
            'qty' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'assigned_tailor_id' => $tailorB->id,
            'item_name' => 'Shirt',
            'qty' => 1,
            'unit_price' => 45000,
            'line_total' => 45000,
        ]);

        Livewire::test(OrderShow::class, ['order' => $order])
            ->assertDontSeeHtml('wire:click="openAssignTailorModal"');
    }

    public function test_create_order_can_assign_different_tailors_per_line_item(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $tailorA = $this->createUserWithRole('tailor', $this->branch);
        $tailorB = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->set('lines', [
                [
                    'id' => null,
                    'assigned_tailor_id' => $tailorA->id,
                    'item_name' => 'Shirt',
                    'qty' => 1,
                    'unit_price' => 40000,
                    'line_total' => 40000,
                    'notes' => '',
                    'measurements' => [['key' => '', 'value' => '']],
                ],
                [
                    'id' => null,
                    'assigned_tailor_id' => $tailorB->id,
                    'item_name' => 'Trouser',
                    'qty' => 1,
                    'unit_price' => 35000,
                    'line_total' => 35000,
                    'notes' => '',
                    'measurements' => [['key' => '', 'value' => '']],
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()
            ->with('lines')
            ->latest('id')
            ->first();

        $this->assertNotNull($order);
        $this->assertNull($order->assigned_tailor_id);

        $lineTailorIds = $order->lines()
            ->orderBy('id')
            ->pluck('assigned_tailor_id')
            ->all();

        $this->assertSame([$tailorA->id, $tailorB->id], $lineTailorIds);
    }

    public function test_order_level_tailor_overrides_line_tailors_on_save(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $orderTailor = $this->createUserWithRole('tailor', $this->branch);
        $lineTailorA = $this->createUserWithRole('tailor', $this->branch);
        $lineTailorB = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        Livewire::test(OrderForm::class)
            ->set('customer_id', $customer->id)
            ->set('assigned_tailor_id', $orderTailor->id)
            ->set('lines', [
                [
                    'id' => null,
                    'assigned_tailor_id' => $lineTailorA->id,
                    'item_name' => 'Shirt',
                    'qty' => 1,
                    'unit_price' => 50000,
                    'line_total' => 50000,
                    'notes' => '',
                    'measurements' => [['key' => '', 'value' => '']],
                ],
                [
                    'id' => null,
                    'assigned_tailor_id' => $lineTailorB->id,
                    'item_name' => 'Trouser',
                    'qty' => 1,
                    'unit_price' => 45000,
                    'line_total' => 45000,
                    'notes' => '',
                    'measurements' => [['key' => '', 'value' => '']],
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::query()
            ->with('lines')
            ->latest('id')
            ->first();

        $this->assertNotNull($order);
        $this->assertSame($orderTailor->id, (int) $order->assigned_tailor_id);
        $this->assertTrue(
            $order->lines->every(fn ($line) => $line->assigned_tailor_id === null)
        );
    }

    public function test_line_tailor_selector_is_hidden_when_order_tailor_is_selected(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $orderTailor = $this->createUserWithRole('tailor', $this->branch);

        Livewire::test(OrderForm::class)
            ->assertSee('Line Tailor')
            ->set('assigned_tailor_id', $orderTailor->id)
            ->assertDontSee('Line Tailor');
    }

    public function test_edit_order_can_update_existing_order_expense_values(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'assigned_tailor_id' => $tailor->id,
            'status' => OrderStatus::New,
            'subtotal' => 70000,
            'discount' => 0,
            'total' => 70000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => auth()->id(),
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'item_name' => 'Suit',
            'qty' => 1,
            'unit_price' => 70000,
            'line_total' => 70000,
        ]);

        $expense = OrderExpense::create([
            'order_id' => $order->id,
            'tailor_id' => $tailor->id,
            'amount' => 12000,
            'notes' => 'Initial labor',
        ]);

        Livewire::test(OrderForm::class, ['order' => $order])
            ->set('order_expenses.0.notes', 'Labour Charge')
            ->set('order_expenses.0.amount', 18000)
            ->call('save')
            ->assertHasNoErrors();

        $expense->refresh();
        $this->assertSame('Labour Charge', $expense->notes);
        $this->assertEquals(18000.0, (float) $expense->amount);
    }

    public function test_edit_order_accepts_its_original_historical_order_date(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $originalOrderDate = today()->subDays(10)->toDateString();

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'order_date' => $originalOrderDate,
            'status' => OrderStatus::New,
            'subtotal' => 70000,
            'discount' => 0,
            'total' => 70000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => auth()->id(),
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'item_name' => 'Suit',
            'qty' => 1,
            'unit_price' => 70000,
            'line_total' => 70000,
        ]);

        Livewire::test(OrderForm::class, ['order' => $order])
            ->set('notes', 'Updated without changing the original date')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($originalOrderDate, $order->fresh()->order_date->toDateString());
    }

    public function test_edit_order_can_add_and_remove_order_expenses(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'assigned_tailor_id' => $tailor->id,
            'status' => OrderStatus::New,
            'subtotal' => 60000,
            'discount' => 0,
            'total' => 60000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => auth()->id(),
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'item_name' => 'Shirt',
            'qty' => 1,
            'unit_price' => 60000,
            'line_total' => 60000,
        ]);

        $oldExpense = OrderExpense::create([
            'order_id' => $order->id,
            'tailor_id' => $tailor->id,
            'amount' => 10000,
            'notes' => 'Old expense',
        ]);

        Livewire::test(OrderForm::class, ['order' => $order])
            ->set('order_expenses', [
                [
                    'id' => null,
                    'tailor_id' => null,
                    'notes' => 'Other',
                    'amount' => 4500,
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('order_expenses', ['id' => $oldExpense->id]);

        $newExpense = OrderExpense::query()
            ->where('order_id', $order->id)
            ->where('notes', 'Other')
            ->first();

        $this->assertNotNull($newExpense);
        $this->assertSame($tailor->id, $newExpense->tailor_id);
        $this->assertEquals(4500.0, (float) $newExpense->amount);
    }
}

<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
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
}

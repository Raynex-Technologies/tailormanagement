<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaymentRecorded;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Orders\OrderPaymentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    protected OrderPaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake events to prevent listeners from firing during tests
        Event::fake([OrderPaymentRecorded::class]);

        PaymentMethod::query()->updateOrCreate(
            ['id' => 1],
            ['name' => 'Default']
        );

        $this->paymentService = app(OrderPaymentService::class);
    }

    public function test_recording_payment_updates_payment_status_correctly(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress,
            'due_date' => now()->addDays(7),
            'subtotal' => 100000,
            'discount' => 0,
            'total' => 100000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        // Record partial payment
        $this->paymentService->recordPayment($order, [
            'amount' => 50000,
            'payment_method_id' => 1,
        ], $user);

        $order->refresh();
        $this->assertEquals(PaymentStatus::Partial, $order->payment_status);

        // Record remaining payment
        $this->paymentService->recordPayment($order, [
            'amount' => 50000,
            'payment_method_id' => 1,
        ], $user);

        $order->refresh();
        $this->assertEquals(PaymentStatus::Paid, $order->payment_status);
    }

    public function test_recording_payment_updates_balance_correctly(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress,
            'due_date' => now()->addDays(7),
            'subtotal' => 100000,
            'discount' => 0,
            'total' => 100000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $this->assertEquals(100000, $order->balance_due);
        $this->assertEquals(0, $order->paid_amount);

        // Record payment
        $this->paymentService->recordPayment($order, [
            'amount' => 40000,
            'payment_method_id' => 1,
        ], $user);

        $order->refresh();
        $this->assertEquals(60000, $order->balance_due);
        $this->assertEquals(40000, $order->paid_amount);
    }

    public function test_overpayment_is_blocked_for_non_admin(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
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

        $this->expectException(ValidationException::class);

        // Try to pay more than total
        $this->paymentService->recordPayment($order, [
            'amount' => 60000, // More than 50000 total
            'payment_method_id' => 1,
        ], $user);
    }

    public function test_payment_creates_payment_record(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress,
            'due_date' => now()->addDays(7),
            'subtotal' => 100000,
            'discount' => 0,
            'total' => 100000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $payment = $this->paymentService->recordPayment($order, [
            'amount' => 30000,
            'payment_method_id' => 1,
            'reference' => 'REF123',
            'note' => 'Test payment',
        ], $user);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'branch_id' => $this->branch->id,
            'amount' => 30000,
            'payment_method_id' => 1,
            'reference' => 'REF123',
            'received_by' => $user->id,
        ]);
    }

    public function test_multiple_payments_accumulate_correctly(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress,
            'due_date' => now()->addDays(7),
            'subtotal' => 100000,
            'discount' => 0,
            'total' => 100000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        // First payment
        $this->paymentService->recordPayment($order, [
            'amount' => 20000,
            'payment_method_id' => 1,
        ], $user);

        // Second payment
        $this->paymentService->recordPayment($order, [
            'amount' => 30000,
            'payment_method_id' => 1,
        ], $user);

        // Third payment
        $this->paymentService->recordPayment($order, [
            'amount' => 25000,
            'payment_method_id' => 1,
        ], $user);

        $order->refresh();

        $this->assertEquals(75000, $order->paid_amount);
        $this->assertEquals(25000, $order->balance_due);
        $this->assertEquals(3, $order->payments()->count());
        $this->assertEquals(PaymentStatus::Partial, $order->payment_status);
    }
}

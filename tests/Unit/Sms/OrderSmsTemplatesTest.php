<?php

namespace Tests\Unit\Sms;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\SmsTemplate;
use App\Services\Sms\Templates\OrderSmsTemplates;
use Tests\TestCase;

class OrderSmsTemplatesTest extends TestCase
{
    public function test_status_change_variables_include_order_dates_and_garments(): void
    {
        $variables = SmsTemplate::variablesForCategory('order_status_change');

        $this->assertContains('garments', $variables);
        $this->assertContains('order_date', $variables);
        $this->assertContains('due_date', $variables);
        $this->assertContains('status', $variables);
    }

    public function test_order_created_template_renders_garments_and_dates(): void
    {
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Amina',
        ]);

        $orderDate = now()->toDateString();
        $dueDate = now()->addDays(5)->toDateString();

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'order_no' => 'ORD-TEST-001',
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => $orderDate,
            'due_date' => $dueDate,
            'subtotal' => 90000,
            'discount' => 0,
            'total' => 90000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => null,
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'item_name' => 'Suit',
            'qty' => 2,
            'unit_price' => 30000,
            'line_total' => 60000,
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'item_name' => 'Shirt',
            'qty' => 1,
            'unit_price' => 30000,
            'line_total' => 30000,
        ]);

        SmsTemplate::instance()->update([
            'templates' => array_replace(SmsTemplate::defaultTemplates(), [
                'order_created' => 'Order {order_number} {garments} {order_date} {due_date}',
            ]),
        ]);

        $message = OrderSmsTemplates::orderCreated($order->fresh());

        $this->assertSame(
            sprintf(
                'Order ORD-TEST-001 Suit x2, Shirt %s %s',
                now()->format('M d, Y'),
                now()->addDays(5)->format('M d, Y')
            ),
            $message
        );
    }

    public function test_due_date_changed_template_renders_old_and_new_due_dates(): void
    {
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Baraka',
        ]);

        $oldDueDate = now()->addDays(3)->toDateString();
        $newDueDate = now()->addDays(8)->toDateString();

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'order_no' => 'ORD-TEST-002',
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress,
            'order_date' => now()->toDateString(),
            'due_date' => $newDueDate,
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => null,
        ]);

        SmsTemplate::instance()->update([
            'templates' => array_replace(SmsTemplate::defaultTemplates(), [
                'order_delivery_date_change' => 'From {old_due_date} to {new_due_date} for {order_number}',
            ]),
        ]);

        $message = OrderSmsTemplates::dueDateChanged($order->fresh(), $oldDueDate, $newDueDate);

        $this->assertSame(
            sprintf(
                'From %s to %s for ORD-TEST-002',
                now()->addDays(3)->format('M d, Y'),
                now()->addDays(8)->format('M d, Y')
            ),
            $message
        );
    }
}

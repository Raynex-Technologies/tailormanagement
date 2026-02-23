<?php

namespace Tests\Feature\Invoices;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Livewire\Administration\BusinessSettings;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    public function test_order_creation_generates_invoice_with_lines(): void
    {
        $user = $this->actingAsRole('admin', $this->branch);

        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'priority' => Priority::Normal,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $line = $order->lines()->create([
            'item_name' => 'Custom Suit',
            'qty' => 2,
            'unit_price' => 150000,
            'line_total' => 300000,
        ]);

        $order->recalculateTotals();
        $order->refresh();

        $invoice = Invoice::where('order_id', $order->id)->first();

        $this->assertNotNull($invoice);
        $this->assertSame($order->id, $invoice->order_id);
        $this->assertSame((string) $order->total, (string) $invoice->total);
        $this->assertSame(1, $invoice->lines()->count());
        $this->assertSame($line->item_name, $invoice->lines()->first()->item_name);
    }

    public function test_admin_can_save_business_settings(): void
    {
        $this->actingAsRole('admin', $this->branch);

        Livewire::test(BusinessSettings::class)
            ->set('business_name', 'Raynex Tailors')
            ->set('phone', '+255700000000')
            ->set('email', 'info@raynex.test')
            ->call('saveBusinessSettings')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_settings', [
            'id' => 1,
            'business_name' => 'Raynex Tailors',
            'phone' => '+255700000000',
            'email' => 'info@raynex.test',
        ]);
    }
}

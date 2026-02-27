<?php

namespace Tests\Feature\Invoices;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Livewire\Administration\BusinessSettings;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Carbon\CarbonImmutable;
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

    public function test_invoice_numbers_use_incremental_sequence_with_prefix_and_year(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 1, 10, 0, 0));

        try {
            $user = $this->actingAsRole('admin', $this->branch);
            $customer = Customer::factory()->create([
                'branch_id' => $this->branch->id,
            ]);

            $firstOrder = Order::create([
                'branch_id' => $this->branch->id,
                'customer_id' => $customer->id,
                'status' => OrderStatus::New,
                'priority' => Priority::Normal,
                'subtotal' => 10000,
                'discount' => 0,
                'total' => 10000,
                'payment_status' => PaymentStatus::Unpaid,
                'created_by' => $user->id,
            ]);

            $secondOrder = Order::create([
                'branch_id' => $this->branch->id,
                'customer_id' => $customer->id,
                'status' => OrderStatus::New,
                'priority' => Priority::Normal,
                'subtotal' => 20000,
                'discount' => 0,
                'total' => 20000,
                'payment_status' => PaymentStatus::Unpaid,
                'created_by' => $user->id,
            ]);

            $firstInvoice = Invoice::where('order_id', $firstOrder->id)->firstOrFail();
            $secondInvoice = Invoice::where('order_id', $secondOrder->id)->firstOrFail();

            $this->assertMatchesRegularExpression('/^INV-2026-\d{6}$/', $firstInvoice->invoice_no);
            $this->assertMatchesRegularExpression('/^INV-2026-\d{6}$/', $secondInvoice->invoice_no);

            $firstSequence = (int) substr($firstInvoice->invoice_no, -6);
            $secondSequence = (int) substr($secondInvoice->invoice_no, -6);

            $this->assertSame($firstSequence + 1, $secondSequence);
        } finally {
            CarbonImmutable::setTestNow();
        }
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

<?php

namespace Tests\Feature\Invoices;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Livewire\Administration\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Support\InvoiceTemplateResolver;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceTemplateSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::query()->updateOrCreate(
            ['id' => 1],
            ['name' => 'Default']
        );
    }

    public function test_invoice_templates_are_seeded_by_migration(): void
    {
        $this->assertSame(6, InvoiceTemplate::query()->count());
        $this->assertDatabaseHas('invoice_templates', ['slug' => 'tailwind', 'is_default' => true]);
        $this->assertDatabaseHas('invoice_templates', ['slug' => 'classic']);
        $this->assertDatabaseHas('invoice_templates', ['slug' => 'modern']);
        $this->assertDatabaseHas('invoice_templates', ['slug' => 'minimal']);
        $this->assertDatabaseHas('invoice_templates', ['slug' => 'bold']);
        $this->assertDatabaseHas('invoice_templates', ['slug' => 'elegant']);
    }

    public function test_admin_can_save_invoice_template_selection_in_settings(): void
    {
        $this->actingAsRole('admin', $this->branch);

        $modernTemplate = InvoiceTemplate::query()->where('slug', 'modern')->firstOrFail();

        Livewire::test(BusinessSettings::class)
            ->set('tab', 'invoice_templates')
            ->set('invoice_template_id', $modernTemplate->id)
            ->call('saveInvoiceTemplateSettings')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_settings', [
            'id' => 1,
            'invoice_template_id' => $modernTemplate->id,
        ]);
    }

    public function test_resolver_falls_back_to_default_when_selected_template_is_inactive(): void
    {
        $this->actingAsRole('admin', $this->branch);

        $settings = BusinessSetting::instance();
        $modernTemplate = InvoiceTemplate::query()->where('slug', 'modern')->firstOrFail();
        $settings->update(['invoice_template_id' => $modernTemplate->id]);
        $modernTemplate->update(['is_active' => false]);

        $resolved = app(InvoiceTemplateResolver::class)->resolve($settings->fresh());

        $this->assertSame('tailwind', $resolved->slug);
    }

    public function test_invoice_print_and_download_use_selected_template(): void
    {
        $this->actingAsRole('admin', $this->branch);

        $modernTemplate = InvoiceTemplate::query()->where('slug', 'modern')->firstOrFail();
        $modernTemplate->update(['is_active' => true]);
        BusinessSetting::instance()->update(['invoice_template_id' => $modernTemplate->id]);

        $invoice = $this->createInvoice();

        $printResponse = $this->get(route('invoices.print', $invoice));
        $printResponse->assertOk();
        $printResponse->assertSee('Modern template');

        $pdfResponse = $this->get(route('invoices.download', $invoice));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('[MODERN TEMPLATE]', $pdfResponse->streamedContent());
    }

    public function test_invoice_download_falls_back_to_default_template_when_selected_template_is_inactive(): void
    {
        $this->actingAsRole('admin', $this->branch);

        $modernTemplate = InvoiceTemplate::query()->where('slug', 'modern')->firstOrFail();
        BusinessSetting::instance()->update(['invoice_template_id' => $modernTemplate->id]);
        $modernTemplate->update(['is_active' => false]);

        $invoice = $this->createInvoice();

        $pdfResponse = $this->get(route('invoices.download', $invoice));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('Tailwind Basic', $pdfResponse->streamedContent());
    }

    public function test_settings_route_requires_existing_settings_permission(): void
    {
        $this->actingAsRole('tailor', $this->branch);

        $this->get(route('administration.settings'))->assertForbidden();
    }

    private function createInvoice(): Invoice
    {
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Template Client',
        ]);

        $order = Order::query()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress,
            'priority' => Priority::Normal,
            'due_date' => now()->addDays(7),
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => auth()->id(),
        ]);

        $order->lines()->create([
            'item_name' => 'Bespoke Jacket',
            'qty' => 1,
            'unit_price' => 180000,
            'line_total' => 180000,
        ]);

        $order->recalculateTotals();

        return Invoice::query()->where('order_id', $order->id)->firstOrFail();
    }
}

<?php

namespace Tests\Feature\Invoices;

use App\Livewire\Administration\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Support\Invoices\InvoicePreviewDataFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceTemplatePreviewTest extends TestCase
{
    public function test_settings_shows_every_registered_template_with_full_page_preview_actions_and_clear_active_state(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $activeTemplate = InvoiceTemplate::query()->where('slug', 'tailwind')->firstOrFail();
        BusinessSetting::instance()->update(['invoice_template_id' => $activeTemplate->id]);

        $component = Livewire::test(BusinessSettings::class)->set('tab', 'invoice_templates');
        $document = $this->document($component->html());
        $xpath = new DOMXPath($document);
        $cards = $xpath->query('//*[@data-invoice-template-card]');

        $this->assertCount(InvoiceTemplate::query()->where('is_active', true)->count(), $cards);
        $this->assertCount($cards->length, $xpath->query('//*[@data-invoice-template-thumbnail]'));

        foreach ($cards as $card) {
            $this->assertCount(1, $xpath->query('.//button[contains(normalize-space(.), "Preview")]', $card));
        }

        $activeCards = $xpath->query('//*[@data-active-invoice-template="true"]');
        $this->assertCount(1, $activeCards);
        $this->assertStringContainsString('Tailwind Basic', $activeCards->item(0)->textContent);
        $this->assertStringContainsString('Currently Active', $activeCards->item(0)->textContent);
        $this->assertStringNotContainsString('Use This Template', $activeCards->item(0)->textContent);
        $component->assertSee('documentWidth: 794', false);
        $component->assertSee('documentHeight: 1123', false);
        $component->assertSee('this.viewport = this.$el', false);
        $component->assertSee('this.viewport?.clientWidth', false);
        $component->assertSee("querySelector('.invoice-shell')", false);
        $component->assertSee('data-thumbnail-scale-to-fit', false);
        $component->assertSee('h-[360px]', false);
        $component->assertSee('sm:h-[400px]', false);
        $component->assertSee('xl:h-[420px]', false);
        $component->assertSee('availableWidth / this.documentWidth', false);
        $component->assertSee('availableHeight / this.documentHeight', false);
        $component->assertSee('translate(${offsetX}px, ${offsetY}px) scale(${scale})', false);
        $component->assertSee('md:grid-cols-2 xl:grid-cols-3', false);
        $component->assertDontSee('const availableWidth = this.$el.clientWidth', false);
        $component->assertDontSee('displayHeight', false);
    }

    public function test_requested_template_preview_uses_canonical_sample_contract_without_persisting_records_or_changing_active_template(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $activeTemplate = InvoiceTemplate::query()->where('slug', 'modern')->firstOrFail();
        $previewTemplate = InvoiceTemplate::query()->where('slug', 'tailwind')->firstOrFail();
        BusinessSetting::instance()->update(['invoice_template_id' => $activeTemplate->id]);

        $counts = $this->previewTableCounts();

        $this->get(route('administration.settings.invoice-template-preview', $previewTemplate->id))
            ->assertOk()
            ->assertSee('data-invoice-template="tailwind"', false)
            ->assertSee('Tailwind Basic')
            ->assertSee('INV-2026-003979')
            ->assertSee('ORD-2026-252273')
            ->assertSee('Decines Smith')
            ->assertSee("Men's Suit")
            ->assertSee('Shirt')
            ->assertSee('Alteration Service')
            ->assertSee('Executive Tailoring Package')
            ->assertSee('Monogram Embroidery')
            ->assertSee('1,947,000')
            ->assertSee('Final fitting is required before collection.');

        $this->assertSame($counts, $this->previewTableCounts());
        $this->assertSame($activeTemplate->id, BusinessSetting::instance()->fresh()->invoice_template_id);
    }

    public function test_every_registered_template_renders_through_the_canonical_preview_without_persisting_records(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $activeTemplateId = BusinessSetting::instance()->invoice_template_id;
        $counts = $this->previewTableCounts();

        $templates = InvoiceTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($templates as $template) {
            $this->get(route('administration.settings.invoice-template-preview', $template->id))
                ->assertOk()
                ->assertSee('data-invoice-template="'.$template->slug.'"', false)
                ->assertSee('INV-2026-003979');
        }

        $this->assertSame($counts, $this->previewTableCounts());
        $this->assertSame($activeTemplateId, BusinessSetting::instance()->fresh()->invoice_template_id);
    }

    public function test_opening_an_inactive_relative_template_preview_does_not_activate_it(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $tailwind = InvoiceTemplate::query()->where('slug', 'tailwind')->firstOrFail();
        $modern = InvoiceTemplate::query()->where('slug', 'modern')->firstOrFail();
        BusinessSetting::instance()->update(['invoice_template_id' => $tailwind->id]);

        Livewire::test(BusinessSettings::class)
            ->set('tab', 'invoice_templates')
            ->call('openInvoiceTemplatePreview', $modern->id)
            ->assertSet('showInvoiceTemplatePreview', true)
            ->assertSet('preview_invoice_template_id', $modern->id)
            ->assertSee('data-invoice-template-preview-dialog', false)
            ->assertSee('data-invoice-template-frame="modal"', false)
            ->assertSee('data-fit-page-preview', false)
            ->assertSee('!max-w-[1000px]', false)
            ->assertSee('h-[52dvh]', false)
            ->assertSee('availableWidth / this.documentWidth', false)
            ->assertSee('availableHeight / this.documentHeight', false)
            ->assertSee('scrolling="no"', false)
            ->assertDontSee('h-[calc(100dvh-13rem)]', false)
            ->assertSee('Use This Template');

        $this->assertSame($tailwind->id, BusinessSetting::instance()->fresh()->invoice_template_id);
    }

    public function test_authorized_administrator_can_activate_from_preview_and_active_ui_updates_immediately(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $modern = InvoiceTemplate::query()->where('slug', 'modern')->firstOrFail();

        $component = Livewire::test(BusinessSettings::class)
            ->set('tab', 'invoice_templates')
            ->call('openInvoiceTemplatePreview', $modern->id)
            ->call('activateInvoiceTemplate', $modern->id, true)
            ->assertHasNoErrors()
            ->assertSet('invoice_template_id', $modern->id)
            ->assertSet('showInvoiceTemplatePreview', false)
            ->assertSet('preview_invoice_template_id', null)
            ->assertSee('Invoice template updated successfully.');

        $this->assertSame($modern->id, BusinessSetting::instance()->fresh()->invoice_template_id);

        $xpath = new DOMXPath($this->document($component->html()));
        $activeCards = $xpath->query('//*[@data-active-invoice-template="true"]');
        $this->assertCount(1, $activeCards);
        $this->assertStringContainsString('Modern', $activeCards->item(0)->textContent);
        $this->assertStringNotContainsString('Use This Template', $activeCards->item(0)->textContent);
    }

    public function test_unregistered_template_identifiers_are_rejected_without_changing_settings(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $activeTemplateId = BusinessSetting::instance()->invoice_template_id;

        Livewire::test(BusinessSettings::class)
            ->set('tab', 'invoice_templates')
            ->call('openInvoiceTemplatePreview', 999999)
            ->assertHasErrors('invoice_template_id')
            ->assertSet('showInvoiceTemplatePreview', false)
            ->call('activateInvoiceTemplate', 999999)
            ->assertHasErrors('invoice_template_id');

        $this->get(route('administration.settings.invoice-template-preview', 999999))->assertNotFound();
        $this->assertSame($activeTemplateId, BusinessSetting::instance()->fresh()->invoice_template_id);
    }

    public function test_template_render_failure_is_sanitized_and_failed_template_cannot_be_activated(): void
    {
        $this->actingAsRole('admin', $this->branch);
        $tailwind = InvoiceTemplate::query()->where('slug', 'tailwind')->firstOrFail();
        $modern = InvoiceTemplate::query()->where('slug', 'modern')->firstOrFail();
        BusinessSetting::instance()->update(['invoice_template_id' => $tailwind->id]);
        $modern->update(['blade_view' => 'invoices.templates.missing-preview-view']);

        $this->get(route('administration.settings.invoice-template-preview', $modern->id))
            ->assertUnprocessable()
            ->assertSee('Preview unavailable')
            ->assertDontSee('missing-preview-view');

        Livewire::test(BusinessSettings::class)
            ->set('tab', 'invoice_templates')
            ->call('openInvoiceTemplatePreview', $modern->id)
            ->assertHasErrors('invoice_template_id')
            ->assertSet('showInvoiceTemplatePreview', false)
            ->call('activateInvoiceTemplate', $modern->id)
            ->assertHasErrors('invoice_template_id');

        $this->assertSame($tailwind->id, BusinessSetting::instance()->fresh()->invoice_template_id);
    }

    public function test_preview_factory_returns_unsaved_canonical_models(): void
    {
        $counts = $this->previewTableCounts();
        $preview = app(InvoicePreviewDataFactory::class)->make(BusinessSetting::instance());

        $this->assertInstanceOf(Invoice::class, $preview['invoice']);
        $this->assertFalse($preview['invoice']->exists);
        $this->assertFalse($preview['invoice']->order->exists);
        $this->assertFalse($preview['invoice']->order->customer->exists);
        $this->assertCount(8, $preview['invoice']->lines);
        $this->assertTrue($preview['invoice']->relationLoaded('lines'));
        $this->assertTrue($preview['invoice']->order->relationLoaded('payments'));
        $this->assertSame($counts, $this->previewTableCounts());
    }

    public function test_unauthorized_user_cannot_access_template_preview(): void
    {
        $this->actingAsRole('tailor', $this->branch);
        $template = InvoiceTemplate::query()->where('slug', 'tailwind')->firstOrFail();
        BusinessSetting::instance()->update(['invoice_template_id' => $template->id]);

        $this->get(route('administration.settings.invoice-template-preview', $template->id))->assertForbidden();

        try {
            app(BusinessSettings::class)->activateInvoiceTemplate($template->id);
            $this->fail('An unauthorized user activated an invoice template.');
        } catch (AuthorizationException) {
            $this->assertSame($template->id, BusinessSetting::instance()->fresh()->invoice_template_id);
        }
    }

    /** @return array<string, int> */
    private function previewTableCounts(): array
    {
        return [
            'customers' => Customer::query()->count(),
            'orders' => Order::query()->count(),
            'invoices' => Invoice::query()->count(),
            'order_payments' => OrderPayment::query()->count(),
            'payment_methods' => PaymentMethod::query()->count(),
        ];
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return $document;
    }
}

<?php

namespace Tests\Feature\Settings;

use App\Livewire\Administration\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\InvoiceTemplate;
use Livewire\Livewire;
use Tests\TestCase;

class MissingInvoiceTemplatesTest extends TestCase
{
    public function test_settings_remain_accessible_and_editable_without_invoice_templates(): void
    {
        $this->actingAsRole('admin');
        BusinessSetting::instance()->update(['invoice_template_id' => null]);
        InvoiceTemplate::query()->delete();
        config(['app.debug' => false]);

        $this->get(route('administration.settings'))->assertOk();
        $this->get(route('administration.settings', ['tab' => 'invoice_templates']))
            ->assertOk()
            ->assertSee('No invoice templates are available.');

        Livewire::test(BusinessSettings::class)
            ->set('business_name', 'Updated Business')
            ->call('saveBusinessSettings')
            ->assertHasNoErrors();

        $this->assertSame('Updated Business', BusinessSetting::instance()->business_name);
        $this->assertDatabaseCount('invoice_templates', 0);
    }

    public function test_missing_templates_do_not_bypass_settings_authorization(): void
    {
        $this->actingAsRole('sales');
        BusinessSetting::instance()->update(['invoice_template_id' => null]);
        InvoiceTemplate::query()->delete();

        $this->get(route('administration.settings'))->assertForbidden();
    }
}

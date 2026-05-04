<?php

namespace Tests\Feature\Settings;

use App\Livewire\Sms\BeemConfigurations;
use App\Models\BeemConfig;
use App\Models\SmsTemplate;
use Livewire\Livewire;
use Tests\TestCase;

class SmsNotificationSettingsTest extends TestCase
{
    public function test_authorized_user_can_view_sms_settings(): void
    {
        $this->actingAsRole('admin');

        $this->get(route('beem-configurations.index'))
            ->assertOk()
            ->assertSee('SMS Settings');
    }

    public function test_unauthorized_user_cannot_view_sms_settings(): void
    {
        $this->actingAsRole('sales');

        $this->get(route('beem-configurations.index'))->assertForbidden();
    }

    public function test_authorized_user_can_update_global_and_template_toggles(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(BeemConfigurations::class)
            ->set('sms_enabled', false)
            ->set('templateEnabled.order_created', true)
            ->set('templateEnabled.order_status_change', false)
            ->call('saveNotificationSettings')
            ->assertHasNoErrors()
            ->assertSee('SMS notification settings updated successfully.');

        $this->assertFalse(BeemConfig::instance()->fresh()->sms_enabled);

        $settings = SmsTemplate::normalizeTemplateSettings(SmsTemplate::instance()->fresh()->template_settings);
        $this->assertTrue($settings['order_created']['sms_enabled']);
        $this->assertFalse($settings['order_status_change']['sms_enabled']);
    }

    public function test_unauthorized_user_cannot_update_sms_notification_settings(): void
    {
        $this->actingAsRole('sales');

        Livewire::test(BeemConfigurations::class)
            ->assertForbidden();
    }
}

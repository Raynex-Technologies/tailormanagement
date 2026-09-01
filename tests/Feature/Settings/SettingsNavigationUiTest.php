<?php

namespace Tests\Feature\Settings;

use App\Livewire\Administration\BusinessSettings;
use App\Livewire\Administration\EmailSetup;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsNavigationUiTest extends TestCase
{
    public function test_settings_navigation_uses_a_single_scrollable_non_wrapping_row(): void
    {
        $this->actingAsRole('admin');

        $this->get(route('administration.settings'))
            ->assertOk()
            ->assertSee('data-settings-navigation', false)
            ->assertSee('data-settings-tabs-scroller', false)
            ->assertSee('overflow-x-auto', false)
            ->assertSee('settings-tabs-scroller', false)
            ->assertSee('.settings-tabs-scroller::-webkit-scrollbar', false)
            ->assertSee('scrollbar-width: none', false)
            ->assertSee('shrink-0', false)
            ->assertSee('min-h-10', false)
            ->assertSee('first:ml-auto last:mr-auto', false)
            ->assertSee('bg-lime-400 text-navy-900', false)
            ->assertSee('aria-label="Settings sections"', false)
            ->assertSee('Email Settings')
            ->assertSee('href="'.route('administration.email-setup').'"', false)
            ->assertDontSee('flex-wrap gap-2 border-b', false);
    }

    public function test_email_settings_tab_uses_the_existing_authorized_email_setup_workflow(): void
    {
        $this->actingAsRole('superadmin');

        $settingsResponse = $this->get(route('administration.settings'))->assertOk();

        $this->assertSame([
            'Business Settings',
            'Email Settings',
            'Order Settings',
            'Payment Methods',
            'Tax Settings',
            'Invoice Templates',
            'System UI Settings',
        ], $this->settingsTabLabels($settingsResponse->getContent()));

        $emailResponse = $this->get(route('administration.email-setup'))
            ->assertOk()
            ->assertSee('data-settings-navigation', false)
            ->assertSee('data-email-delivery-controls', false);

        $this->assertSame('Email Settings', $this->activeTabLabel($emailResponse->getContent()));

        Livewire::test(EmailSetup::class)
            ->assertSet('tab', 'smtp')
            ->assertSee('Customer Email Delivery')
            ->assertSee('Outgoing SMTP Server');

        $this->get(route('administration.settings', ['tab' => 'invoice_templates']))
            ->assertOk()
            ->assertSee('data-invoice-template-grid', false);
    }

    public function test_active_settings_tab_uses_aria_current_and_keeps_existing_livewire_state(): void
    {
        $this->actingAsRole('admin');

        $component = Livewire::test(BusinessSettings::class)
            ->assertSet('tab', 'business');

        $this->assertSame('Business Settings', $this->activeTabLabel($component->html()));

        $component->set('tab', 'invoice_templates')->assertSet('tab', 'invoice_templates');
        $this->assertSame('Invoice Templates', $this->activeTabLabel($component->html()));

        $component->set('tab', 'system_ui')->assertSet('tab', 'system_ui');
        $this->assertSame('System UI Settings', $this->activeTabLabel($component->html()));
    }

    public function test_permission_restricted_settings_tab_remains_hidden(): void
    {
        $role = Role::create(['name' => 'settings_without_system_ui', 'guard_name' => 'web']);
        $role->givePermissionTo('roles.manage');
        $user = $this->createUserWithRole('settings_without_system_ui', $this->branch);

        $this->actingAs($user);

        $this->get(route('administration.settings'))
            ->assertOk()
            ->assertDontSee('System UI Settings');

        Livewire::test(BusinessSettings::class)
            ->set('tab', 'system_ui')
            ->assertDontSee('System UI Settings');
    }

    private function activeTabLabel(string $html): string
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $activeTabs = (new \DOMXPath($document))->query('//*[@data-settings-tab][@aria-current="page"]');

        $this->assertCount(1, $activeTabs);

        return trim($activeTabs->item(0)->textContent);
    }

    /** @return array<int, string> */
    private function settingsTabLabels(string $html): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $tabs = (new \DOMXPath($document))->query('//*[@data-settings-tab]');
        $labels = [];

        foreach ($tabs as $tab) {
            $labels[] = trim($tab->textContent);
        }

        return $labels;
    }
}

<?php

namespace Tests\Feature\Settings;

use App\Livewire\Administration\BusinessSettings;
use App\Models\BusinessSetting;
use App\Support\SystemUiSettings;
use Livewire\Livewire;
use Tests\TestCase;

class SystemUiSettingsTest extends TestCase
{
    public function test_authorized_user_can_view_system_ui_settings_tab(): void
    {
        $this->actingAsRole('admin');

        $this->get(route('administration.settings'))
            ->assertOk()
            ->assertSee('System UI Settings');
    }

    public function test_unauthorized_user_cannot_view_or_update_system_ui_settings(): void
    {
        $user = $this->actingAsRole('sales');

        $this->get(route('administration.settings'))->assertForbidden();

        $this->assertFalse($user->can('settings.system-ui.view'));
        $this->assertFalse($user->can('settings.system-ui.update'));
    }

    public function test_valid_hex_colors_can_be_saved_and_normalized(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(BusinessSettings::class)
            ->set('ui_primary_color', '#abc')
            ->set('ui_secondary_color_1', '#2563eb')
            ->set('ui_secondary_color_2', '#F59E0B')
            ->call('saveSystemUiSettings')
            ->assertHasNoErrors()
            ->assertSee('System UI settings updated successfully.');

        $settings = BusinessSetting::instance()->fresh();

        $this->assertSame('#AABBCC', $settings->ui_primary_color);
        $this->assertSame('#2563EB', $settings->ui_secondary_color_1);
        $this->assertSame('#F59E0B', $settings->ui_secondary_color_2);
    }

    public function test_saved_theme_colors_drive_semantic_tokens_and_survive_reload(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(BusinessSettings::class)
            ->set('ui_primary_color', '#123456')
            ->set('ui_secondary_color_1', '#EEDD22')
            ->set('ui_secondary_color_2', '#22CCAA')
            ->call('saveSystemUiSettings')
            ->assertHasNoErrors();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--tm-sidebar: #123456;', false)
            ->assertSee('--tm-hero: #123456;', false)
            ->assertSee('--tm-primary-action: #EEDD22;', false)
            ->assertSee('--tm-primary-action-foreground: #111827;', false)
            ->assertSee('--tm-accent: #22CCAA;', false);

        SystemUiSettings::clearCache();

        $this->get(route('dashboard'))
            ->assertSee('--tm-sidebar: #123456;', false)
            ->assertSee('--tm-primary-action: #EEDD22;', false)
            ->assertSee('--tm-accent: #22CCAA;', false);
    }

    public function test_modern_shell_and_page_heroes_consume_semantic_theme_tokens(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $sidebar = file_get_contents(resource_path('views/layouts/app/sidebar.blade.php'));
        $orders = file_get_contents(resource_path('views/livewire/orders/index.blade.php'));
        $invoices = file_get_contents(resource_path('views/livewire/invoices/index.blade.php'));
        $users = file_get_contents(resource_path('views/livewire/users/index.blade.php'));

        $this->assertStringContainsString('var(--tm-sidebar)', $sidebar);
        $this->assertStringContainsString('var(--tm-primary-action)', $sidebar);
        $settingsNavigation = file_get_contents(resource_path('views/components/administration/settings-navigation.blade.php'));
        $catalog = file_get_contents(resource_path('views/livewire/order-catalog/index.blade.php'));

        $this->assertStringContainsString('var(--tm-accent)', $sidebar);
        $this->assertStringContainsString('app-profile-accent', $sidebar);
        $this->assertStringContainsString('var(--tm-primary-action)', $css);
        $this->assertStringContainsString('var(--tm-hero)', $orders);
        $this->assertStringContainsString('var(--tm-hero)', $invoices);
        $this->assertStringContainsString('var(--tm-hero)', $users);
        $this->assertStringContainsString('tm-active', $settingsNavigation);
        $this->assertStringContainsString('tm-active', $catalog);
        $this->assertStringContainsString('[data-flux-breadcrumbs-item] > a > svg', $css);
        $this->assertStringContainsString('[role="tab"][aria-selected="true"]', $css);
        $this->assertSame('#111827', SystemUiSettings::foreground('#F4F4A1'));
        $this->assertSame('#FFFFFF', SystemUiSettings::foreground('#1B315F'));
        $this->assertStringContainsString('.badge-warning', $css);
        $this->assertStringContainsString('#D97706', $css);
    }

    public function test_invalid_color_values_are_rejected(): void
    {
        $this->actingAsRole('admin');

        foreach (['red', 'rgb(0,0,0)', 'var(--x)', 'url(javascript:alert(1))', '#12345', '#zzzzzz', '<script>alert(1)</script>'] as $unsafeColor) {
            Livewire::test(BusinessSettings::class)
                ->set('ui_primary_color', $unsafeColor)
                ->set('ui_secondary_color_1', '#2563EB')
                ->set('ui_secondary_color_2', '#F59E0B')
                ->call('saveSystemUiSettings')
                ->assertHasErrors(['ui_primary_color']);
        }
    }

    public function test_missing_ui_settings_fall_back_to_defaults(): void
    {
        BusinessSetting::instance()->update([
            'ui_primary_color' => null,
            'ui_secondary_color_1' => null,
            'ui_secondary_color_2' => null,
        ]);
        SystemUiSettings::clearCache();

        $this->assertSame([
            'primary' => '#111827',
            'secondary_1' => '#FE6328',
            'secondary_2' => '#A3E635',
        ], SystemUiSettings::colors());
    }

    public function test_reset_to_defaults_works(): void
    {
        $this->actingAsRole('admin');

        BusinessSetting::instance()->update([
            'ui_primary_color' => '#000000',
            'ui_secondary_color_1' => '#111111',
            'ui_secondary_color_2' => '#222222',
        ]);

        Livewire::test(BusinessSettings::class)
            ->call('resetSystemUiSettings')
            ->assertHasNoErrors();

        $settings = BusinessSetting::instance()->fresh();

        $this->assertSame(SystemUiSettings::DEFAULT_PRIMARY, $settings->ui_primary_color);
        $this->assertSame(SystemUiSettings::DEFAULT_SECONDARY_1, $settings->ui_secondary_color_1);
        $this->assertSame(SystemUiSettings::DEFAULT_SECONDARY_2, $settings->ui_secondary_color_2);
    }

    public function test_layout_renders_saved_css_variables_and_never_unsafe_values(): void
    {
        BusinessSetting::instance()->update([
            'ui_primary_color' => '#123456',
            'ui_secondary_color_1' => '#654321',
            'ui_secondary_color_2' => 'url(javascript:alert(1))',
        ]);
        SystemUiSettings::clearCache();

        $this->actingAsRole('admin');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--tm-sidebar: #123456;', false)
            ->assertSee('--tm-hero: #123456;', false)
            ->assertSee('--tm-primary-action: #654321;', false)
            ->assertSee('--tm-accent: #A3E635;', false)
            ->assertSee('--tailorpro-primary: var(--tm-sidebar);', false)
            ->assertDontSee('url(javascript:alert(1))', false);
    }
}

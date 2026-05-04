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
            'secondary_1' => '#2563EB',
            'secondary_2' => '#F59E0B',
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
            ->assertSee('--tailorpro-primary: #123456;', false)
            ->assertSee('--tailorpro-secondary: #654321;', false)
            ->assertSee('--tailorpro-secondary-2: #F59E0B;', false)
            ->assertDontSee('url(javascript:alert(1))', false);
    }
}

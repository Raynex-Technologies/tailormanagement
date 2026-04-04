<?php

namespace App\Livewire\Storefront\Admin;

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Support\StorefrontMedia;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app.sidebar')]
#[Title('Storefront Settings')]
class Settings extends Component
{
    use WithFileUploads;

    public bool $storefront_enabled = false;
    public bool $storefront_catalog_mode = false;
    public bool $custom_order_portal_enabled = true;
    public bool $guest_checkout_enabled = true;
    public bool $allow_cash_on_delivery = false;
    public string $storefront_maintenance_message = '';
    public string $storefront_currency = 'TZS';
    public string $storefront_contact_email = '';
    public string $storefront_contact_phone = '';
    public string $storefront_address = '';
    public string $storefront_seo_title = '';
    public string $storefront_seo_description = '';
    public string $storefront_announcement_text = '';
    public string $storefront_announcement_link = '';
    public bool $storefront_announcement_bar_enabled = false;
    public ?int $storefront_default_branch_id = null;

    public $storefrontLogoUpload = null;
    public $storefrontFaviconUpload = null;
    public $storefrontHeroUpload = null;
    public $storefrontSocialUpload = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('storefront.settings.manage'), 403);

        $settings = BusinessSetting::instance();
        $this->storefront_enabled = (bool) $settings->storefront_enabled;
        $this->storefront_catalog_mode = (bool) $settings->storefront_catalog_mode;
        $this->custom_order_portal_enabled = (bool) $settings->custom_order_portal_enabled;
        $this->guest_checkout_enabled = (bool) $settings->guest_checkout_enabled;
        $this->allow_cash_on_delivery = (bool) $settings->allow_cash_on_delivery;
        $this->storefront_maintenance_message = (string) ($settings->storefront_maintenance_message ?? '');
        $this->storefront_currency = strtoupper((string) ($settings->storefront_currency ?: 'TZS'));
        $this->storefront_contact_email = (string) ($settings->storefront_contact_email ?? '');
        $this->storefront_contact_phone = (string) ($settings->storefront_contact_phone ?? '');
        $this->storefront_address = (string) ($settings->storefront_address ?? '');
        $this->storefront_seo_title = (string) ($settings->storefront_seo_title ?? '');
        $this->storefront_seo_description = (string) ($settings->storefront_seo_description ?? '');
        $this->storefront_announcement_bar_enabled = (bool) $settings->storefront_announcement_bar_enabled;
        $this->storefront_announcement_text = (string) ($settings->storefront_announcement_text ?? '');
        $this->storefront_announcement_link = (string) ($settings->storefront_announcement_link ?? '');
        $this->storefront_default_branch_id = $settings->storefront_default_branch_id;
    }

    public function save(): void
    {
        $this->authorize('storefront.settings.manage');

        $validated = $this->validate([
            'storefront_enabled' => ['boolean'],
            'storefront_catalog_mode' => ['boolean'],
            'custom_order_portal_enabled' => ['boolean'],
            'guest_checkout_enabled' => ['boolean'],
            'allow_cash_on_delivery' => ['boolean'],
            'storefront_maintenance_message' => ['nullable', 'string', 'max:4000'],
            'storefront_currency' => ['required', 'string', 'size:3'],
            'storefront_contact_email' => ['nullable', 'email', 'max:191'],
            'storefront_contact_phone' => ['nullable', 'string', 'max:50'],
            'storefront_address' => ['nullable', 'string', 'max:1000'],
            'storefront_seo_title' => ['nullable', 'string', 'max:191'],
            'storefront_seo_description' => ['nullable', 'string', 'max:1000'],
            'storefront_announcement_bar_enabled' => ['boolean'],
            'storefront_announcement_text' => ['nullable', 'string', 'max:191'],
            'storefront_announcement_link' => ['nullable', 'url', 'max:191'],
            'storefront_default_branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'storefrontLogoUpload' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
                'dimensions:min_width=64,min_height=64,max_width=3000,max_height=3000',
            ],
            'storefrontFaviconUpload' => [
                'nullable',
                'image',
                'mimes:png,webp',
                'mimetypes:image/png,image/webp',
                'max:512',
                'dimensions:min_width=32,min_height=32,max_width=512,max_height=512',
            ],
            'storefrontHeroUpload' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:6144',
                'dimensions:min_width=320,min_height=180,max_width=5000,max_height=5000',
            ],
            'storefrontSocialUpload' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:3072',
                'dimensions:min_width=200,min_height=200,max_width=5000,max_height=5000',
            ],
        ]);

        $settings = BusinessSetting::instance();

        $data = [
            'storefront_enabled' => (bool) $validated['storefront_enabled'],
            'storefront_catalog_mode' => (bool) $validated['storefront_catalog_mode'],
            'custom_order_portal_enabled' => (bool) $validated['custom_order_portal_enabled'],
            'guest_checkout_enabled' => (bool) $validated['guest_checkout_enabled'],
            'allow_cash_on_delivery' => (bool) $validated['allow_cash_on_delivery'],
            'storefront_maintenance_message' => $validated['storefront_maintenance_message'] ?: null,
            'storefront_currency' => strtoupper($validated['storefront_currency']),
            'storefront_contact_email' => $validated['storefront_contact_email'] ?: null,
            'storefront_contact_phone' => $validated['storefront_contact_phone'] ?: null,
            'storefront_address' => $validated['storefront_address'] ?: null,
            'storefront_seo_title' => $validated['storefront_seo_title'] ?: null,
            'storefront_seo_description' => $validated['storefront_seo_description'] ?: null,
            'storefront_announcement_bar_enabled' => (bool) $validated['storefront_announcement_bar_enabled'],
            'storefront_announcement_text' => $validated['storefront_announcement_text'] ?: null,
            'storefront_announcement_link' => $validated['storefront_announcement_link'] ?: null,
            'storefront_default_branch_id' => $validated['storefront_default_branch_id'] ?: null,
        ];

        if ($this->storefrontLogoUpload) {
            if ($settings->storefront_logo_path) {
                StorefrontMedia::delete($settings->storefront_logo_path);
            }

            $data['storefront_logo_path'] = StorefrontMedia::store($this->storefrontLogoUpload, 'storefront/media');
        }

        if ($this->storefrontFaviconUpload) {
            if ($settings->storefront_favicon_path) {
                StorefrontMedia::delete($settings->storefront_favicon_path);
            }

            $data['storefront_favicon_path'] = StorefrontMedia::store($this->storefrontFaviconUpload, 'storefront/media');
        }

        if ($this->storefrontHeroUpload) {
            if ($settings->storefront_hero_media_path) {
                StorefrontMedia::delete($settings->storefront_hero_media_path);
            }

            $data['storefront_hero_media_path'] = StorefrontMedia::store($this->storefrontHeroUpload, 'storefront/media');
        }

        if ($this->storefrontSocialUpload) {
            if ($settings->storefront_social_image_path) {
                StorefrontMedia::delete($settings->storefront_social_image_path);
            }

            $data['storefront_social_image_path'] = StorefrontMedia::store($this->storefrontSocialUpload, 'storefront/media');
        }

        $settings->update($data);

        session()->flash('success', 'Storefront settings updated successfully.');
        $this->mount();
    }

    public function render()
    {
        return view('livewire.storefront.admin.settings', [
            'settings' => BusinessSetting::instance(),
            'branches' => Branch::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}

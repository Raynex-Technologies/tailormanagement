<div>
    <flux:main class="p-0">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Storefront') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Settings') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl">{{ __('Storefront Settings') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('Configure storefront availability, customer portal behavior, contact details, and storefront media.') }}
            </flux:text>
        </div>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <flux:card class="mt-6 space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Storefront Controls') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                    {{ __('Enable or disable storefront sales and customer portal behavior.') }}
                </flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <span class="text-sm font-medium">{{ __('Storefront Enabled') }}</span>
                    <input type="checkbox" wire:model="storefront_enabled" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <span class="text-sm font-medium">{{ __('Catalog Mode (No Checkout)') }}</span>
                    <input type="checkbox" wire:model="storefront_catalog_mode" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <span class="text-sm font-medium">{{ __('Custom Order Portal') }}</span>
                    <input type="checkbox" wire:model="custom_order_portal_enabled" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <span class="text-sm font-medium">{{ __('Guest Checkout') }}</span>
                    <input type="checkbox" wire:model="guest_checkout_enabled" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <span class="text-sm font-medium">{{ __('Allow Cash on Delivery') }}</span>
                    <input type="checkbox" wire:model="allow_cash_on_delivery" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <span class="text-sm font-medium">{{ __('Announcement Bar') }}</span>
                    <input type="checkbox" wire:model="storefront_announcement_bar_enabled" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model.blur="storefront_currency" label="{{ __('Currency (ISO)') }}" placeholder="TZS" maxlength="3" />
                <flux:select wire:model.blur="storefront_default_branch_id" label="{{ __('Default Storefront Branch') }}">
                    <flux:select.option value="">{{ __('Auto (first active branch)') }}</flux:select.option>
                    @foreach ($branches as $branch)
                        <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.blur="storefront_contact_email" type="email" label="{{ __('Storefront Contact Email') }}" />
                <flux:input wire:model.blur="storefront_contact_phone" label="{{ __('Storefront Contact Phone') }}" />
                <flux:input wire:model.blur="storefront_seo_title" label="{{ __('SEO Title') }}" />
                <flux:input wire:model.blur="storefront_announcement_text" label="{{ __('Announcement Text') }}" />
                <flux:input wire:model.blur="storefront_announcement_link" label="{{ __('Announcement Link URL') }}" />
                <flux:input wire:model.blur="storefront_seo_description" label="{{ __('SEO Description') }}" />
            </div>

            <flux:textarea wire:model.blur="storefront_address" label="{{ __('Storefront Address') }}" rows="2" />
            <flux:textarea wire:model.blur="storefront_maintenance_message" label="{{ __('Maintenance Message') }}" rows="2" />

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:label>{{ __('Storefront Logo') }}</flux:label>
                    <input type="file" wire:model="storefrontLogoUpload" accept="image/*" class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600" />
                    @if ($settings->storefront_logo_url)
                        <img src="{{ $settings->storefront_logo_url }}" alt="Storefront logo" class="mt-2 h-12 rounded border border-zinc-200 dark:border-zinc-700" />
                    @endif
                </div>
                <div>
                    <flux:label>{{ __('Favicon') }}</flux:label>
                    <input type="file" wire:model="storefrontFaviconUpload" accept="image/*" class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600" />
                    @if ($settings->storefront_favicon_url)
                        <img src="{{ $settings->storefront_favicon_url }}" alt="Storefront favicon" class="mt-2 h-10 rounded border border-zinc-200 dark:border-zinc-700" />
                    @endif
                </div>
                <div>
                    <flux:label>{{ __('Hero Media') }}</flux:label>
                    <input type="file" wire:model="storefrontHeroUpload" accept="image/*" class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600" />
                    @if ($settings->storefront_hero_media_url)
                        <img src="{{ $settings->storefront_hero_media_url }}" alt="Storefront hero" class="mt-2 h-16 rounded border border-zinc-200 dark:border-zinc-700" />
                    @endif
                </div>
                <div>
                    <flux:label>{{ __('Social Share Image') }}</flux:label>
                    <input type="file" wire:model="storefrontSocialUpload" accept="image/*" class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600" />
                    @if ($settings->storefront_social_image_url)
                        <img src="{{ $settings->storefront_social_image_url }}" alt="Storefront social image" class="mt-2 h-16 rounded border border-zinc-200 dark:border-zinc-700" />
                    @endif
                </div>
            </div>

            <div class="flex justify-end">
                <flux:button type="button" variant="primary" wire:click="save">
                    <x-icon name="check" class="mr-1 size-4" />
                    {{ __('Save Storefront Settings') }}
                </flux:button>
            </div>
        </flux:card>
    </flux:main>
</div>

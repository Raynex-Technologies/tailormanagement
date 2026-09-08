<div>
    <flux:main class="p-0">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Administration') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Settings') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl">{{ __('Business Settings') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('Manage business profile, orders, payments, taxes, and invoice settings.') }}
            </flux:text>
        </div>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <x-administration.settings-navigation :active="$tab" :wire-tabs="true" />

        @if ($tab === 'business')
            <flux:card class="mt-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model.blur="business_name" label="{{ __('Business Name') }}" required />
                    <flux:input wire:model.blur="phone" label="{{ __('Phone') }}" />
                    <flux:input wire:model.blur="alternate_phone" label="{{ __('Alternate Phone') }}" />
                    <flux:input wire:model.blur="email" type="email" label="{{ __('Email') }}" />
                    <flux:input wire:model.blur="tin_number" label="{{ __('TIN Number (Optional)') }}" />
                </div>

                <div class="mt-4">
                    <flux:textarea wire:model.blur="address" label="{{ __('Address') }}" rows="3" />
                </div>

                <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                    <flux:label>{{ __('Business Logo') }}</flux:label>

                    @if ($logoUpload)
                        <div class="mt-2 flex items-center gap-3">
                            <img src="{{ $logoUpload->temporaryUrl() }}" alt="{{ __('New logo preview') }}" class="h-16 w-auto rounded-md border border-zinc-200 bg-white p-1 dark:border-zinc-700">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('New logo selected. Save settings to apply it.') }}
                            </p>
                        </div>
                    @endif

                    @if ($settings->logo_url)
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <img src="{{ $settings->logo_url }}" alt="{{ __('Logo') }}" class="h-16 w-auto rounded-md border border-zinc-200 bg-white p-1 dark:border-zinc-700">
                            <flux:button type="button" size="sm" variant="ghost" wire:click="removeLogo">
                                {{ __('Remove Logo') }}
                            </flux:button>
                        </div>
                    @elseif (! $logoUpload)
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('No logo uploaded yet.') }}
                        </p>
                    @endif

                    <input
                        type="file"
                        wire:model="logoUpload"
                        accept="image/*"
                        class="mt-3 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600"
                    />
                    <p wire:loading wire:target="logoUpload" class="mt-1 text-xs text-zinc-500">{{ __('Uploading logo...') }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('PNG, JPG, WEBP. Max 2MB.') }}</p>
                </div>

                <div class="mt-6 flex justify-end">
                    <flux:button type="button" variant="primary" wire:click="saveBusinessSettings">
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ __('Save Business Settings') }}
                    </flux:button>
                </div>
            </flux:card>
        @endif

        @if ($tab === 'orders')
            <flux:card class="mt-6 space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Order Settings') }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                        {{ __('Control how dates are handled when orders are created or edited.') }}
                    </flux:text>
                </div>

                <label class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                    <span>
                        <span class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Allow Order Dates Flexibility') }}</span>
                        <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('When enabled, order date and due date may be earlier than today. When disabled, both dates must be today or later.') }}
                        </span>
                    </span>
                    <input
                        type="checkbox"
                        wire:model="allow_order_dates_flexibility"
                        class="size-5 rounded border-zinc-300 text-lime-600 focus:ring-lime-500"
                    />
                </label>

                <div class="flex justify-end">
                    <flux:button type="button" variant="primary" wire:click="saveOrderSettings">
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ __('Save Order Settings') }}
                    </flux:button>
                </div>
            </flux:card>
        @endif

        @if ($tab === 'system_ui')
            @can('settings.system-ui.view')
                @php
                    $previewPrimary = \App\Support\SystemUiSettings::normalize($ui_primary_color) ?? \App\Support\SystemUiSettings::DEFAULT_PRIMARY;
                    $previewSecondary = \App\Support\SystemUiSettings::normalize($ui_secondary_color_1) ?? \App\Support\SystemUiSettings::DEFAULT_SECONDARY_1;
                    $previewAccent = \App\Support\SystemUiSettings::normalize($ui_secondary_color_2) ?? \App\Support\SystemUiSettings::DEFAULT_SECONDARY_2;
                    $previewPrimaryText = \App\Support\SystemUiSettings::foreground($previewPrimary);
                    $previewSecondaryText = \App\Support\SystemUiSettings::foreground($previewSecondary);
                    $previewAccentText = \App\Support\SystemUiSettings::foreground($previewAccent);
                @endphp

                <flux:card class="mt-6 space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('System UI Settings') }}</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                            {{ __('Customize the main colors used across your TailorPro application.') }}
                        </flux:text>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="space-y-4">
                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <flux:label>{{ __('Navigation & Page Hero') }}</flux:label>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Used for the main sidebar and major page headers.') }}</p>
                                    </div>
                                    <input type="color" wire:model.live="ui_primary_color" value="{{ $previewPrimary }}" class="h-10 w-14 rounded border border-zinc-300 bg-transparent p-1 dark:border-zinc-600" />
                                </div>
                                <input type="text" wire:model.live.debounce.300ms="ui_primary_color" placeholder="#111827" class="mt-3 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                                @error('ui_primary_color') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <flux:label>{{ __('Primary Action') }}</flux:label>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Used for primary call-to-action buttons such as New Order and Save.') }}</p>
                                    </div>
                                    <input type="color" wire:model.live="ui_secondary_color_1" value="{{ $previewSecondary }}" class="h-10 w-14 rounded border border-zinc-300 bg-transparent p-1 dark:border-zinc-600" />
                                </div>
                                <input type="text" wire:model.live.debounce.300ms="ui_secondary_color_1" placeholder="#FE6328" class="mt-3 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                                @error('ui_secondary_color_1') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <flux:label>{{ __('Application Accent') }}</flux:label>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Used for active navigation, selected tabs, focus accents, and application highlights.') }}</p>
                                    </div>
                                    <input type="color" wire:model.live="ui_secondary_color_2" value="{{ $previewAccent }}" class="h-10 w-14 rounded border border-zinc-300 bg-transparent p-1 dark:border-zinc-600" />
                                </div>
                                <input type="text" wire:model.live.debounce.300ms="ui_secondary_color_2" placeholder="#A3E635" class="mt-3 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                                @error('ui_secondary_color_2') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Live Preview') }}</h3>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Sidebar, active item, button, and accent badge.') }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background-color: {{ $previewAccent }}; color: {{ $previewAccentText }};">{{ __('Accent') }}</span>
                            </div>

                            <div class="overflow-hidden rounded-xl shadow-sm">
                                <div class="p-4" style="background-color: {{ $previewPrimary }}; color: {{ $previewPrimaryText }};">
                                    <div class="mb-4 flex items-center gap-3">
                                        <div class="flex size-9 items-center justify-center rounded-lg font-bold" style="background-color: {{ $previewAccent }}; color: {{ $previewAccentText }};">T</div>
                                        <div class="font-semibold">{{ $business_name ?: __('TailorPro') }}</div>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="rounded-lg px-3 py-2 text-sm opacity-75">{{ __('Dashboard') }}</div>
                                        <div class="rounded-lg px-3 py-2 text-sm font-semibold" style="background-color: {{ $previewAccent }}; color: {{ $previewAccentText }};">{{ __('Active Menu Item') }}</div>
                                        <div class="rounded-lg px-3 py-2 text-sm opacity-75">{{ __('Orders') }}</div>
                                    </div>
                                </div>
                                <div class="space-y-4 bg-white p-4 dark:bg-zinc-900">
                                    <button type="button" class="rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition hover:opacity-90" style="background-color: {{ $previewSecondary }}; color: {{ $previewSecondaryText }};">
                                        {{ __('Primary Button') }}
                                    </button>
                                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                        <div class="mb-2 h-2 w-24 rounded-full" style="background-color: {{ $previewAccent }};"></div>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Important action highlights and focus accents use the secondary accent color.') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @can('settings.system-ui.update')
                        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <flux:button
                                type="button"
                                variant="ghost"
                                wire:click="resetSystemUiSettings"
                                wire:confirm="{{ __('Are you sure you want to reset the UI colors to the default TailorPro colors?') }}"
                            >
                                {{ __('Reset Theme to Default') }}
                            </flux:button>
                            <flux:button type="button" variant="primary" wire:click="saveSystemUiSettings">
                                <x-icon name="check" class="mr-1 size-4" />
                                {{ __('Save Changes') }}
                            </flux:button>
                        </div>
                    @endcan
                </flux:card>
            @endcan
        @endif

        @if ($tab === 'payment_methods')
            <flux:card class="mt-6 space-y-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Payment Methods') }}</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                            {{ __('Configure available payment methods used for deposits and order payments.') }}
                        </flux:text>
                        <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Online gateway credentials can be saved per payment method. Pesapal still falls back to .env keys when per-method credentials are not set.') }}
                        </flux:text>
                    </div>

                    <flux:button type="button" variant="primary" wire:click="openCreatePaymentMethodModal">
                        <x-icon name="add" class="mr-1 size-4" />
                        {{ __('New Payment Method') }}
                    </flux:button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('Method') }}</th>
                                <th class="px-4 py-3">{{ __('Code') }}</th>
                                <th class="px-4 py-3">{{ __('Type') }}</th>
                                <th class="px-4 py-3">{{ __('Enabled') }}</th>
                                <th class="px-4 py-3">{{ __('On Invoices') }}</th>
                                <th class="px-4 py-3">{{ __('Account Number') }}</th>
                                <th class="px-4 py-3">{{ __('Account Holder') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($paymentMethods as $method)
                                <tr class="text-sm text-zinc-900 dark:text-zinc-100">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ $method->name }}</span>
                                            @if ($method->id === 1)
                                                <flux:badge size="sm">{{ __('Default') }}</flux:badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-500">{{ $method->code ?: '-' }}</td>
                                    <td class="px-4 py-3 text-zinc-500">{{ ucfirst($method->type ?? 'offline') }}</td>
                                    <td class="px-4 py-3">
                                        <flux:badge size="sm" :color="$method->is_enabled ? 'green' : 'zinc'">
                                            {{ $method->is_enabled ? __('Enabled') : __('Disabled') }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-4 py-3">{{ $method->show_on_invoice ? __('Yes') : __('No') }}</td>
                                    <td class="px-4 py-3 text-zinc-500">{{ $method->account_number ?: '-' }}</td>
                                    <td class="px-4 py-3 text-zinc-500">{{ $method->account_holder_name ?: '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end">
                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button type="button" size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Actions for :method', ['method' => $method->name]) }}" />
                                                <flux:menu>
                                                    <flux:menu.item icon="pencil-square" wire:click="editPaymentMethod({{ $method->id }})">
                                                        {{ __('Edit') }}
                                                    </flux:menu.item>
                                                    @if ($method->id !== 1)
                                                        <flux:menu.item icon="trash" variant="danger" wire:click="deletePaymentMethod({{ $method->id }})">
                                                            {{ __('Delete') }}
                                                        </flux:menu.item>
                                                    @endif
                                                </flux:menu>
                                            </flux:dropdown>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('No payment methods configured.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <flux:modal wire:model="showPaymentMethodModal" class="max-w-3xl">
                    <div class="space-y-4">
                        <flux:heading size="lg">{{ $editingPaymentMethodId ? __('Edit Payment Method') : __('New Payment Method') }}</flux:heading>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <flux:input wire:model.blur="paymentMethodName" label="{{ __('Method Name') }}" placeholder="{{ __('e.g. Bank Transfer') }}" required />
                            <flux:input wire:model.blur="paymentMethodCode" label="{{ __('Method Code') }}" placeholder="{{ __('e.g. pesapal') }}" required />
                            <flux:select wire:model.blur="paymentMethodType" label="{{ __('Type') }}">
                                <flux:select.option value="offline">{{ __('Offline') }}</flux:select.option>
                                <flux:select.option value="online">{{ __('Online') }}</flux:select.option>
                            </flux:select>
                            <flux:input wire:model.blur="paymentMethodAccountNumber" label="{{ __('Account Number') }}" placeholder="{{ __('Optional') }}" />
                            <flux:input wire:model.blur="paymentMethodAccountHolderName" label="{{ __('Account Holder Name') }}" placeholder="{{ __('Optional') }}" />
                            <flux:input wire:model.blur="paymentMethodSortOrder" type="number" min="0" label="{{ __('Sort Order') }}" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                                <span class="text-sm font-medium">{{ __('Enabled at Checkout') }}</span>
                                <input type="checkbox" wire:model="paymentMethodEnabled" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                            </label>
                            <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                                <span class="text-sm font-medium">{{ __('Online Gateway') }}</span>
                                <input type="checkbox" wire:model="paymentMethodOnline" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                            </label>
                        </div>

                        <flux:switch wire:model="paymentMethodShowOnInvoice" label="{{ __('Show on printed and downloaded invoices') }}" description="{{ __('Include this payment method and its account details in the invoice Payment Methods section.') }}" />

                        <flux:textarea wire:model.blur="paymentMethodDescription" label="{{ __('Checkout Description') }}" rows="2" />

                        @php($isOnlineGatewayForm = $this->isOnlineGatewayForm())
                        @php($isPesapalGateway = strtolower(trim((string) $paymentMethodCode)) === 'pesapal')

                        @if ($isOnlineGatewayForm)
                            <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <flux:heading size="sm">{{ __('Gateway Credentials') }}</flux:heading>

                                @if ($isPesapalGateway)
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <flux:input
                                            wire:model.blur="paymentMethodConsumerKey"
                                            label="{{ __('Merchant Consumer Key') }}"
                                            placeholder="{{ __('Required for Pesapal online checkout') }}"
                                        />
                                        <flux:input
                                            wire:model.blur="paymentMethodConsumerSecret"
                                            type="password"
                                            autocomplete="new-password"
                                            label="{{ __('Merchant Consumer Secret') }}"
                                            placeholder="{{ __('Required for Pesapal online checkout') }}"
                                        />
                                    </div>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('These credentials are used for Pesapal /Auth/RequestToken authentication for this payment method.') }}
                                    </flux:text>
                                @else
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Provider-specific gateway fields will appear here based on the method code.') }}
                                    </flux:text>
                                @endif
                            </div>
                        @endif

                        @error('paymentMethodName')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                        @error('paymentMethodCode')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                        @error('paymentMethodConsumerKey')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                        @error('paymentMethodConsumerSecret')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror

                        <div class="flex justify-end gap-2">
                            <flux:button type="button" variant="ghost" wire:click="closePaymentMethodModal">
                                {{ __('Cancel') }}
                            </flux:button>
                            <flux:button type="button" variant="primary" wire:click="savePaymentMethod">
                                <x-icon name="check" class="mr-1 size-4" />
                                {{ $editingPaymentMethodId ? __('Update Method') : __('Add Method') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:modal>
            </flux:card>
        @endif

        @if ($tab === 'tax')
            <flux:card class="mt-6">
                <flux:heading size="lg">{{ __('Tax Settings') }}</flux:heading>
                <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
                    {{ __('Coming soon. Tax rules will be configurable here (tax name, rate, and invoice behavior).') }}
                </flux:text>
            </flux:card>
        @endif

        @if ($tab === 'invoice_templates')
            <flux:card class="mt-6 space-y-6" aria-labelledby="invoice-template-settings-heading">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div class="max-w-3xl">
                        <flux:heading id="invoice-template-settings-heading" size="lg">{{ __('Invoice Templates') }}</flux:heading>
                        <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
                            {{ __('Choose the invoice design used for viewing, printing, PDF downloads and customer email attachments.') }}
                        </flux:text>
                    </div>

                    <div class="shrink-0 rounded-xl border border-lime-300 bg-lime-50 px-3 py-2 dark:border-lime-500/50 dark:bg-lime-400/10" data-current-invoice-template>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-lime-800 dark:text-lime-300">{{ __('Current template') }}</p>
                        <p class="mt-0.5 text-sm font-bold text-zinc-900 dark:text-white">{{ $activeInvoiceTemplate->name }}</p>
                    </div>
                </div>

                @error('invoice_template_id')
                    <flux:callout variant="danger" icon="exclamation-triangle">
                        {{ $message }}
                    </flux:callout>
                @enderror

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3" data-invoice-template-grid>
                    @foreach ($invoiceTemplates as $template)
                        @php($isActiveTemplate = (int) $activeInvoiceTemplate->id === (int) $template->id)

                        <article
                            wire:key="invoice-template-card-{{ $template->id }}"
                            data-invoice-template-card="{{ $template->slug }}"
                            @if ($isActiveTemplate) data-active-invoice-template="true" @endif
                            @class([
                                'flex h-full flex-col overflow-hidden rounded-2xl border bg-white transition dark:bg-zinc-900/50',
                                'border-lime-400 ring-1 ring-lime-300 dark:border-lime-500 dark:ring-lime-500/30' => $isActiveTemplate,
                                'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600' => ! $isActiveTemplate,
                            ])
                        >
                            <div class="border-b border-zinc-200 bg-zinc-200/70 p-3 dark:border-zinc-700 dark:bg-zinc-950/70">
                                <x-invoices.template-preview-frame :template="$template" />
                            </div>

                            <div class="flex flex-1 flex-col gap-4 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">{{ $template->name }}</h3>
                                        <p class="mt-1 min-h-10 text-sm leading-5 text-zinc-500 line-clamp-2 dark:text-zinc-400">
                                            {{ $template->description ?: __('Invoice layout option.') }}
                                        </p>
                                    </div>

                                    @if ($isActiveTemplate)
                                        <span class="shrink-0 rounded-full bg-lime-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-lime-800 dark:bg-lime-400/15 dark:text-lime-300">
                                            {{ __('Currently Active') }}
                                        </span>
                                    @elseif ($template->is_default)
                                        <span class="shrink-0 rounded-full bg-zinc-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                            {{ __('Default') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-auto flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="min-h-10 sm:flex-1"
                                        wire:click="openInvoiceTemplatePreview({{ $template->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="openInvoiceTemplatePreview({{ $template->id }})"
                                    >
                                        {{ __('Preview') }}
                                    </flux:button>

                                    @unless ($isActiveTemplate)
                                        <flux:button
                                            type="button"
                                            size="sm"
                                            variant="primary"
                                            class="min-h-10 sm:flex-1"
                                            wire:click="activateInvoiceTemplate({{ $template->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="activateInvoiceTemplate({{ $template->id }})"
                                        >
                                            {{ __('Use This Template') }}
                                        </flux:button>
                                    @endunless
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </flux:card>

            <flux:modal wire:model="showInvoiceTemplatePreview" class="!w-[calc(100vw-1rem)] !max-w-[1000px] sm:!w-[calc(100vw-3rem)]">
                @if ($previewInvoiceTemplate)
                    @php($previewIsActive = (int) $activeInvoiceTemplate->id === (int) $previewInvoiceTemplate->id)

                    <div class="flex max-h-[calc(100dvh-2rem)] min-h-0 flex-col" data-invoice-template-preview-dialog>
                        <div class="flex items-start justify-between gap-4 border-b border-zinc-200 pb-4 pr-8 dark:border-zinc-700">
                            <div>
                                <flux:heading size="lg">{{ $previewInvoiceTemplate->name }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Complete invoice preview using realistic sample data.') }}
                                </flux:text>
                            </div>

                            @if ($previewIsActive)
                                <span class="shrink-0 rounded-full bg-lime-100 px-3 py-1 text-xs font-bold text-lime-800 dark:bg-lime-400/15 dark:text-lime-300">
                                    {{ __('Currently Active') }}
                                </span>
                            @endif
                        </div>

                        <div class="my-4 min-h-0 flex-1">
                            <x-invoices.template-preview-frame
                                :template="$previewInvoiceTemplate"
                                mode="modal"
                                wire:key="invoice-template-fit-page-preview-{{ $previewInvoiceTemplate->id }}"
                            />
                        </div>

                        <div class="flex flex-col-reverse gap-2 border-t border-zinc-200 pt-4 sm:flex-row sm:justify-end dark:border-zinc-700">
                            <flux:button type="button" variant="ghost" class="min-h-10" wire:click="closeInvoiceTemplatePreview">
                                {{ __('Close') }}
                            </flux:button>

                            @if ($previewIsActive)
                                <span class="inline-flex min-h-10 items-center justify-center rounded-lg bg-zinc-100 px-4 text-sm font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ __('Currently Active') }}
                                </span>
                            @else
                                <flux:button
                                    type="button"
                                    variant="primary"
                                    class="min-h-10"
                                    wire:click="activateInvoiceTemplate({{ $previewInvoiceTemplate->id }}, true)"
                                    wire:loading.attr="disabled"
                                    wire:target="activateInvoiceTemplate({{ $previewInvoiceTemplate->id }}, true)"
                                >
                                    {{ __('Use This Template') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endif
            </flux:modal>
        @endif
    </flux:main>
</div>

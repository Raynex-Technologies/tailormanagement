<div>
    @php
        $previewInvoice = (object) [
            'invoice_no' => 'INV-2026-003979',
            'issue_date' => now(),
            'due_date' => now()->addDays(10),
            'subtotal' => 3460,
            'discount' => 0,
            'tax_amount' => 519,
            'total' => 3979,
            'notes' => __('Sample terms and conditions for template preview.'),
            'order' => (object) [
                'order_no' => 'ORD-2026-001245',
                'status' => \App\Enums\OrderStatus::InProgress,
                'payment_status' => \App\Enums\PaymentStatus::Partial,
                'paid_amount' => 1980,
                'balance_due' => 1999,
                'customer' => (object) [
                    'name' => 'Decines Smith',
                    'phone' => '+039 123 456 7890',
                    'email' => 'decinesmith0123@gmail.com',
                    'address' => '456 Quincy Street, New York, US',
                ],
            ],
            'branch' => (object) [
                'name' => 'Main Branch',
            ],
            'lines' => collect([
                (object) ['item_name' => 'Invoice Design', 'qty' => 1, 'unit_price' => 230, 'line_total' => 230, 'notes' => null],
                (object) ['item_name' => 'UI/UX Design', 'qty' => 3, 'unit_price' => 180, 'line_total' => 540, 'notes' => null],
                (object) ['item_name' => 'Logo Design', 'qty' => 5, 'unit_price' => 130, 'line_total' => 650, 'notes' => null],
                (object) ['item_name' => 'Web Design', 'qty' => 2, 'unit_price' => 340, 'line_total' => 680, 'notes' => null],
                (object) ['item_name' => 'Brochure Design', 'qty' => 3, 'unit_price' => 240, 'line_total' => 720, 'notes' => null],
                (object) ['item_name' => 'Namecard Design', 'qty' => 4, 'unit_price' => 160, 'line_total' => 640, 'notes' => null],
            ]),
        ];

        $previewPaymentMethods = collect([
            (object) [
                'name' => 'Bank Transfer',
                'account_number' => '012 345 678 900',
                'account_holder_name' => 'Nova Musimas',
            ],
        ]);
    @endphp

    <flux:main class="p-0">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Administration') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Settings') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl">{{ __('Business Settings') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('Manage business profile, invoice branding, and email settings.') }}
            </flux:text>
        </div>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <div class="mt-6 flex flex-wrap gap-2 border-b border-zinc-200 dark:border-zinc-700">
            <button
                type="button"
                wire:click="$set('tab', 'business')"
                class="shrink-0 whitespace-nowrap rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'business' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Business Settings') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'email')"
                class="shrink-0 whitespace-nowrap rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'email' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Email Settings') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'orders')"
                class="shrink-0 whitespace-nowrap rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'orders' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Order Settings') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'payment_methods')"
                class="shrink-0 whitespace-nowrap rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'payment_methods' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Payment Methods') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'tax')"
                class="shrink-0 whitespace-nowrap rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'tax' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Tax Settings') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'invoice_templates')"
                class="shrink-0 whitespace-nowrap rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'invoice_templates' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Invoice Templates') }}
            </button>
            @can('settings.system-ui.view')
                <button
                    type="button"
                    wire:click="$set('tab', 'system_ui')"
                    class="shrink-0 whitespace-nowrap rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'system_ui' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
                >
                    {{ __('System UI Settings') }}
                </button>
            @endcan
            </div>
        </div>

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

        @if ($tab === 'email')
            <flux:card class="mt-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model.blur="email_from_name" label="{{ __('From Name') }}" />
                    <flux:input wire:model.blur="email_from_address" type="email" label="{{ __('From Email') }}" />
                    <flux:input wire:model.blur="email_reply_to" type="email" label="{{ __('Reply-To Email') }}" />
                </div>

                <div class="mt-6 flex justify-end">
                    <flux:button type="button" variant="primary" wire:click="saveEmailSettings">
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ __('Save Email Settings') }}
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
                                        <flux:label>{{ __('Primary Color') }}</flux:label>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Used mainly for sidebar background and main brand areas.') }}</p>
                                    </div>
                                    <input type="color" wire:model.live="ui_primary_color" value="{{ $previewPrimary }}" class="h-10 w-14 rounded border border-zinc-300 bg-transparent p-1 dark:border-zinc-600" />
                                </div>
                                <input type="text" wire:model.live.debounce.300ms="ui_primary_color" placeholder="#111827" class="mt-3 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                                @error('ui_primary_color') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <flux:label>{{ __('Secondary Color 1') }}</flux:label>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Used mainly for active buttons, active navigation items, and links.') }}</p>
                                    </div>
                                    <input type="color" wire:model.live="ui_secondary_color_1" value="{{ $previewSecondary }}" class="h-10 w-14 rounded border border-zinc-300 bg-transparent p-1 dark:border-zinc-600" />
                                </div>
                                <input type="text" wire:model.live.debounce.300ms="ui_secondary_color_1" placeholder="#2563EB" class="mt-3 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                                @error('ui_secondary_color_1') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <flux:label>{{ __('Secondary Color 2') }}</flux:label>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Used for highlights, badges, secondary accents, and optional UI emphasis.') }}</p>
                                    </div>
                                    <input type="color" wire:model.live="ui_secondary_color_2" value="{{ $previewAccent }}" class="h-10 w-14 rounded border border-zinc-300 bg-transparent p-1 dark:border-zinc-600" />
                                </div>
                                <input type="text" wire:model.live.debounce.300ms="ui_secondary_color_2" placeholder="#F59E0B" class="mt-3 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
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
                                        <div class="rounded-lg px-3 py-2 text-sm font-semibold" style="background-color: {{ $previewSecondary }}; color: {{ $previewSecondaryText }};">{{ __('Active Menu Item') }}</div>
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
                                {{ __('Reset to Defaults') }}
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
                                <th class="px-4 py-3">{{ __('Account Number') }}</th>
                                <th class="px-4 py-3">{{ __('Account Holder') }}</th>
                                <th class="px-4 py-3">{{ __('Sort') }}</th>
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
                                    <td class="px-4 py-3 text-zinc-500">{{ $method->account_number ?: '-' }}</td>
                                    <td class="px-4 py-3 text-zinc-500">{{ $method->account_holder_name ?: '-' }}</td>
                                    <td class="px-4 py-3 text-zinc-500">{{ $method->sort_order }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <flux:button type="button" size="sm" variant="ghost" wire:click="editPaymentMethod({{ $method->id }})">
                                                {{ __('Edit') }}
                                            </flux:button>
                                            @if ($method->id !== 1)
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="deletePaymentMethod({{ $method->id }})" class="text-red-600">
                                                    {{ __('Delete') }}
                                                </flux:button>
                                            @endif
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
            <flux:card class="mt-6 space-y-6">
                <div class="flex flex-col gap-2">
                    <flux:heading size="lg">{{ __('Invoice Templates') }}</flux:heading>
                    <flux:text class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Choose the layout used for invoice print, email attachment, and PDF download.') }}
                    </flux:text>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Current active template:') }}
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $activeInvoiceTemplate->name }}</span>
                    </p>
                </div>

                @error('invoice_template_id')
                    <p class="text-sm text-red-500">{{ $message }}</p>
                @enderror

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($invoiceTemplates as $template)
                        @php($isSelected = (int) $invoice_template_id === (int) $template->id)

                        <button
                            type="button"
                            wire:click="$set('invoice_template_id', {{ $template->id }})"
                            class="rounded-xl border p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-lime-500 {{ $isSelected ? 'border-lime-500 bg-lime-50/60 dark:border-lime-500 dark:bg-lime-900/20' : 'border-zinc-200 bg-white hover:border-lime-300 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:border-zinc-500' }}"
                        >
                            <input type="radio" class="sr-only" name="invoice_template_id" value="{{ $template->id }}" @checked($isSelected)>

                            @php(ob_start())
                            @includeFirst(
                                [$template->blade_view, 'invoices.templates.classic'],
                                [
                                    'invoice' => $previewInvoice,
                                    'settings' => $settings,
                                    'paymentMethods' => $previewPaymentMethods,
                                    'template' => $template,
                                ]
                            )
                            @php($previewHtml = ob_get_clean())

                            <div class="relative mb-4 h-40 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/60">
                                <iframe
                                    class="pointer-events-none h-full w-full border-0 bg-white"
                                    title="{{ $template->name }} {{ __('preview') }}"
                                    loading="lazy"
                                    sandbox="allow-same-origin"
                                    srcdoc="{{ $previewHtml }}"
                                ></iframe>
                            </div>

                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $template->name }}</h3>
                                <div class="flex items-center gap-1.5">
                                    @if ($template->is_default)
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                            {{ __('Default') }}
                                        </span>
                                    @endif
                                    @if ($isSelected)
                                        <span class="rounded-full bg-lime-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-lime-700 dark:bg-lime-900/40 dark:text-lime-300">
                                            {{ __('Selected') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $template->description ?: __('Invoice layout option.') }}
                            </p>
                            <p class="mt-1 text-[11px] font-mono text-zinc-400 dark:text-zinc-500">
                                {{ $template->blade_view }}
                            </p>
                        </button>
                    @endforeach
                </div>

                <div class="flex justify-end">
                    <flux:button type="button" variant="primary" wire:click="saveInvoiceTemplateSettings">
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ __('Save Invoice Template') }}
                    </flux:button>
                </div>
            </flux:card>
        @endif
    </flux:main>
</div>

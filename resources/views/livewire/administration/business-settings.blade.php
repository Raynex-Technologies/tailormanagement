<div>
    <flux:main class="p-6">
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
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'business' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Business Settings') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'email')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'email' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Email Settings') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'payment_methods')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'payment_methods' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Payment Methods') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'tax')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'tax' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Tax Settings') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'invoice_templates')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'invoice_templates' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Invoice Templates') }}
            </button>
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

        @if ($tab === 'payment_methods')
            <flux:card class="mt-6 space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Payment Methods') }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                        {{ __('Configure available payment methods used for deposits and order payments.') }}
                    </flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model.blur="paymentMethodName" label="{{ __('Method Name') }}" placeholder="{{ __('e.g. Bank Transfer') }}" required />
                    <flux:input wire:model.blur="paymentMethodAccountNumber" label="{{ __('Account Number') }}" placeholder="{{ __('Optional') }}" />
                    <flux:input wire:model.blur="paymentMethodAccountHolderName" label="{{ __('Account Holder Name') }}" placeholder="{{ __('Optional') }}" />
                </div>

                @error('paymentMethodName')
                    <p class="text-sm text-red-500">{{ $message }}</p>
                @enderror

                <div class="flex justify-end gap-2">
                    @if ($editingPaymentMethodId)
                        <flux:button type="button" variant="ghost" wire:click="resetPaymentMethodForm">
                            {{ __('Cancel') }}
                        </flux:button>
                    @endif
                    <flux:button type="button" variant="primary" wire:click="savePaymentMethod">
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ $editingPaymentMethodId ? __('Update Method') : __('Add Method') }}
                    </flux:button>
                </div>

                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('Method') }}</th>
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
                                    <td class="px-4 py-3 text-zinc-500">{{ $method->account_number ?: '-' }}</td>
                                    <td class="px-4 py-3 text-zinc-500">{{ $method->account_holder_name ?: '-' }}</td>
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
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('No payment methods configured.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
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

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($invoiceTemplates as $template)
                        @php($isSelected = (int) $invoice_template_id === (int) $template->id)

                        <button
                            type="button"
                            wire:click="$set('invoice_template_id', {{ $template->id }})"
                            class="rounded-xl border p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-lime-500 {{ $isSelected ? 'border-lime-500 bg-lime-50/60 dark:border-lime-500 dark:bg-lime-900/20' : 'border-zinc-200 bg-white hover:border-lime-300 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:border-zinc-500' }}"
                        >
                            <input type="radio" class="sr-only" name="invoice_template_id" value="{{ $template->id }}" @checked($isSelected)>

                            <div class="relative mb-4 h-40 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/60">
                                <div style="transform: scale(0.23); transform-origin: top left; width: 435%; pointer-events: none;">
                                    @includeFirst(
                                        [$template->blade_view, 'invoices.templates.classic'],
                                        [
                                            'invoice' => $previewInvoice,
                                            'settings' => $settings,
                                            'paymentMethods' => $previewPaymentMethods,
                                            'template' => $template,
                                        ]
                                    )
                                </div>
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

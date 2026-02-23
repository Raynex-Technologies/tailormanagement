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
    </flux:main>
</div>

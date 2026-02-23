<div>
    <flux:main class="p-6">
        {{-- Breadcrumbs --}}
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Administration') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Beem Configurations') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-circle">
                {{ session('error') }}
            </flux:callout>
        @endif

        {{-- Page title --}}
        <flux:heading size="xl" class="mt-2">{{ __('Beem Configurations') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __('Configure Beem SMS credentials, templates, and marketing messages.') }}</flux:text>

        {{-- Tab navigation (sidenav-style) --}}
        <div class="mt-6 flex flex-wrap gap-2 border-b border-zinc-200 dark:border-zinc-700">
            <button
                type="button"
                wire:click="$set('tab', 'credentials')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'credentials' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Credentials') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'templates')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'templates' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('SMS Templates') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'marketing')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'marketing' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Marketing') }}
            </button>
        </div>

        {{-- Tab: Credentials --}}
        @if ($tab === 'credentials')
            <flux:card class="mt-6">
                <div class="space-y-6">
                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div>
                            <flux:heading size="lg">{{ __('Enable SMS') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Turn on SMS sending via Beem. When disabled, no SMS will be sent.') }}</flux:text>
                        </div>
                        <flux:switch wire:model.blur="sms_enabled" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-1 md:grid-cols-2">
                        <flux:input
                            wire:model.blur="api_key"
                            label="{{ __('BEEM API Key') }}"
                            placeholder="Your Beem API key"
                        />
                        <flux:input
                            wire:model.blur="secret_key"
                            type="password"
                            label="{{ __('BEEM Secret Key') }}"
                            placeholder="Your Beem secret key"
                        />
                        <flux:input
                            wire:model.blur="sender_name"
                            label="{{ __('Sender Name') }}"
                            placeholder="e.g. INFO or your registered sender ID"
                        />
                    </div>

                    <flux:button variant="primary" wire:click="saveCredentials" wire:loading.attr="disabled">
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ __('Save Credentials') }}
                    </flux:button>
                </div>
            </flux:card>
        @endif

        {{-- Tab: SMS Templates --}}
        @if ($tab === 'templates')
            <flux:card class="mt-6">
                <flux:heading size="lg" class="mb-4">{{ __('SMS Templates') }}</flux:heading>
                <flux:text class="mb-6 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Use variables in curly braces, e.g. {customer_name}, {order_number}, {expected_delivery_date}, {total_amount}, {amount_paid}, {balance_due}, {status}.') }}
                </flux:text>

                <form wire:submit="saveTemplates" class="space-y-6">
                    @foreach ($categories as $category)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <flux:heading size="md" class="mb-2">{{ $categoryLabels[$category] ?? $category }}</flux:heading>
                            <flux:text class="mb-2 block text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Variables') }}: {{ implode(', ', array_map(fn ($v) => '{' . $v . '}', \App\Models\SmsTemplate::variablesForCategory($category))) }}
                            </flux:text>
                            <flux:textarea
                                wire:model.blur="templates.{{ $category }}"
                                rows="3"
                                placeholder="{{ __('Enter SMS template...') }}"
                                class="w-full"
                            />
                        </div>
                    @endforeach
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ __('Save Templates') }}
                    </flux:button>
                </form>
            </flux:card>
        @endif

        {{-- Tab: Marketing --}}
        @if ($tab === 'marketing')
            <flux:card class="mt-6">
                <flux:heading size="lg" class="mb-4">{{ __('Marketing') }}</flux:heading>
                <flux:text class="mb-6 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Select customers to send a custom SMS. Use {customer_name} in the message. Contacts / broadcast will be available when the contact module is integrated.') }}
                </flux:text>

                <form wire:submit="sendMarketing" class="space-y-6">
                    <div>
                        <flux:label class="mb-2">{{ __('Select customers') }}</flux:label>
                        <div class="max-h-64 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-800">
                            @forelse ($this->customers as $customer)
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                                    <input
                                        type="checkbox"
                                        wire:model.blur="selectedCustomerIds"
                                        value="{{ $customer->id }}"
                                        class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $customer->name }}</span>
                                    <span class="text-sm text-zinc-500">{{ $customer->phone }}</span>
                                </label>
                            @empty
                                <p class="py-4 text-center text-sm text-zinc-500">{{ __('No customers found.') }}</p>
                            @endforelse
                        </div>
                        @error('selectedCustomerIds')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:label class="mb-2">{{ __('Message') }}</flux:label>
                        <flux:textarea
                            wire:model.blur="marketingMessage"
                            rows="4"
                            placeholder="Hello {customer_name}, ..."
                            class="w-full"
                        />
                        <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Use {customer_name} for the recipient name.') }}</flux:text>
                        @error('marketingMessage')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <x-icon name="send" class="mr-1 size-4" />
                        {{ __('Send to selected customers') }}
                    </flux:button>
                </form>
            </flux:card>
        @endif
    </flux:main>
</div>

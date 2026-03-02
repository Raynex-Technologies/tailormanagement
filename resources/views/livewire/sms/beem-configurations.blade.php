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
        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __('Configure Beem SMS credentials, build message templates with quick-insert variables, and prepare marketing messages.') }}</flux:text>

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
                <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('SMS Templates') }}</flux:heading>
                            <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Use the quick-insert buttons instead of typing variables manually. Each button inserts the correct placeholder into the message you are editing.') }}
                            </flux:text>
                        </div>
                        <div class="rounded-xl bg-white px-4 py-3 text-sm text-zinc-600 shadow-sm dark:bg-zinc-900/70 dark:text-zinc-300">
                            {{ __('Templates available') }}: <span class="font-semibold text-zinc-900 dark:text-white">{{ count($categories) }}</span>
                        </div>
                    </div>
                    <div class="grid gap-3 text-sm text-zinc-600 dark:text-zinc-300 md:grid-cols-3">
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/70">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ __('Safer Editing') }}</div>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Clickable variables reduce placeholder typos for non-technical users.') }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/70">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ __('Order Details') }}</div>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Templates can now include garments, order date, due date, balances, and status details.') }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/70">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ __('Due Date Changes') }}</div>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('A dedicated template is now available when an order due date is updated.') }}</p>
                        </div>
                    </div>
                </div>

                <form wire:submit="saveTemplates" class="space-y-6">
                    @foreach ($categories as $category)
                        @php
                            $availableVariables = $categoryVariables[$category] ?? [];
                        @endphp
                        <div
                            x-data="{
                                body: @entangle('templates.'.$category),
                                insertVariable(token) {
                                    const editor = this.$refs.editor;
                                    const value = this.body || '';

                                    if (!editor) {
                                        this.body = value + token;
                                        return;
                                    }

                                    const start = editor.selectionStart ?? value.length;
                                    const end = editor.selectionEnd ?? start;
                                    const prefix = start > 0 && !/\s/.test(value[start - 1]) ? ' ' : '';
                                    const suffix = end < value.length && !/\s/.test(value[end]) ? ' ' : '';

                                    this.body = value.slice(0, start) + prefix + token + suffix + value.slice(end);

                                    this.$nextTick(() => {
                                        editor.focus();
                                        const caret = start + prefix.length + token.length;
                                        editor.setSelectionRange(caret, caret);
                                    });
                                }
                            }"
                            class="overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50"
                        >
                            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <flux:heading size="md">{{ $categoryLabels[$category] ?? $category }}</flux:heading>
                                        <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('Tap a variable to insert it at the current cursor position.') }}
                                        </flux:text>
                                    </div>
                                    <div class="rounded-xl bg-white px-3 py-2 text-xs font-medium text-zinc-600 shadow-sm dark:bg-zinc-900/70 dark:text-zinc-300">
                                        {{ trans_choice(':count variable available|:count variables available', count($availableVariables), ['count' => count($availableVariables)]) }}
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-5 px-5 py-5 xl:grid-cols-[minmax(0,1fr)_320px]">
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <flux:label>{{ __('Template Message') }}</flux:label>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="`${(body || '').length}/1000`"></span>
                                    </div>
                                    <flux:textarea
                                        x-ref="editor"
                                        x-model="body"
                                        rows="4"
                                        placeholder="{{ __('Type the SMS content here...') }}"
                                        class="w-full font-mono text-sm"
                                    />
                                    <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('The selected variables will be replaced automatically when the SMS is generated.') }}
                                    </flux:text>
                                </div>

                                <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/70">
                                    <flux:heading size="sm">{{ __('Quick Insert') }}</flux:heading>
                                    <flux:text class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Click a token below to place it in the message.') }}
                                    </flux:text>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($availableVariables as $variable)
                                            @php
                                                $definition = $variableDefinitions[$variable] ?? [
                                                    'label' => \Illuminate\Support\Str::headline(str_replace('_', ' ', $variable)),
                                                    'description' => '',
                                                ];
                                            @endphp
                                            <button
                                                type="button"
                                                @click="insertVariable(@js('{'.$variable.'}'))"
                                                title="{{ $definition['description'] }}"
                                                class="group rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-left transition hover:border-lime-400 hover:bg-lime-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-lime-500/60 dark:hover:bg-zinc-700/70"
                                            >
                                                <span class="block text-xs font-medium text-zinc-900 dark:text-white">{{ $definition['label'] }}</span>
                                                <span class="mt-1 block font-mono text-xs text-lime-700 dark:text-lime-300">{{ '{' . $variable . '}' }}</span>
                                            </button>
                                        @endforeach
                                    </div>

                                    <div class="mt-4 space-y-2">
                                        @foreach ($availableVariables as $variable)
                                            @php
                                                $definition = $variableDefinitions[$variable] ?? null;
                                            @endphp
                                            @if (!empty($definition['description']))
                                                <div class="rounded-xl bg-zinc-50 px-3 py-2 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                    <span class="font-mono text-zinc-900 dark:text-white">{{ '{' . $variable . '}' }}</span>
                                                    <span class="ml-1">{{ $definition['description'] }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
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
                        <div
                            x-data="{
                                body: @entangle('marketingMessage'),
                                insertVariable(token) {
                                    const editor = this.$refs.editor;
                                    const value = this.body || '';

                                    if (!editor) {
                                        this.body = value + token;
                                        return;
                                    }

                                    const start = editor.selectionStart ?? value.length;
                                    const end = editor.selectionEnd ?? start;
                                    const prefix = start > 0 && !/\s/.test(value[start - 1]) ? ' ' : '';
                                    const suffix = end < value.length && !/\s/.test(value[end]) ? ' ' : '';

                                    this.body = value.slice(0, start) + prefix + token + suffix + value.slice(end);

                                    this.$nextTick(() => {
                                        editor.focus();
                                        const caret = start + prefix.length + token.length;
                                        editor.setSelectionRange(caret, caret);
                                    });
                                }
                            }"
                            class="space-y-3"
                        >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    @click="insertVariable(@js('{customer_name}'))"
                                    class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs font-medium text-zinc-700 transition hover:border-lime-400 hover:bg-lime-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:border-lime-500/60 dark:hover:bg-zinc-700/70"
                                >
                                    {{ __('Insert Customer Name') }}
                                </button>
                            </div>
                            <flux:textarea
                                x-ref="editor"
                                x-model="body"
                                rows="4"
                                placeholder="Hello {customer_name}, ..."
                                class="w-full"
                            />
                        </div>
                        <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Use the quick insert button to add {customer_name}.') }}</flux:text>
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

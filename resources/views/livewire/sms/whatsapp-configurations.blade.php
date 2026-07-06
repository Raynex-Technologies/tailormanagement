<div>
    <flux:main class="p-0">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Administration') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('WhatsApp Settings') }}</flux:breadcrumbs.item>
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

        <flux:heading size="xl" class="mt-2">{{ __('WhatsApp Settings') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
            {{ __('Configure Twilio WhatsApp credentials, choose notification types, and submit WhatsApp templates for Twilio approval.') }}
        </flux:text>

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
                wire:click="$set('tab', 'notifications')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'notifications' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('WhatsApp Settings') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'templates')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'templates' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Templates') }}
            </button>
        </div>

        @if ($tab === 'credentials')
            <flux:card class="mt-6">
                <div class="space-y-6">
                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div>
                            <flux:heading size="lg">{{ __('Enable WhatsApp Messages') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Turn on WhatsApp sending through Twilio. Templates still need individual approval before automated messages are sent.') }}</flux:text>
                        </div>
                        <flux:switch wire:model.blur="whatsapp_enabled" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model.blur="account_sid" label="{{ __('Twilio Account SID') }}" placeholder="AC..." />
                        <flux:input wire:model.blur="auth_token" type="password" label="{{ __('Twilio Auth Token') }}" placeholder="{{ __('Your Twilio auth token') }}" />
                        <flux:input wire:model.blur="from_number" label="{{ __('WhatsApp From Number') }}" placeholder="whatsapp:+14155238886" />
                        <flux:input wire:model.blur="messaging_service_sid" label="{{ __('Messaging Service SID') }}" placeholder="MG..." />
                        <flux:input wire:model.blur="content_template_language" label="{{ __('Template Language') }}" placeholder="en" />
                    </div>

                    <flux:text class="block text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Use either a WhatsApp From Number or a Messaging Service SID. Twilio numbers should include the whatsapp: prefix or an E.164 number.') }}
                    </flux:text>

                    <flux:button variant="primary" wire:click="saveCredentials" wire:loading.attr="disabled">
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ __('Save Credentials') }}
                    </flux:button>
                </div>
            </flux:card>
        @endif

        @if ($tab === 'notifications')
            <form wire:submit="saveNotificationSettings" class="mt-6 space-y-6">
                <flux:card>
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('WhatsApp Notification Settings') }}</flux:heading>
                            <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Choose whether automated notifications can also be sent over WhatsApp.') }}
                            </flux:text>
                        </div>
                        <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $whatsapp_enabled ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' }}">
                            {{ $whatsapp_enabled ? __('WhatsApp Enabled') : __('WhatsApp Disabled') }}
                        </span>
                    </div>

                    <div class="mt-5 flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <div class="pr-4">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Enable WhatsApp Messages') }}</div>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('When turned off, automated WhatsApp messages will not be sent even if templates are enabled.') }}</p>
                        </div>
                        <flux:switch wire:model.live="whatsapp_enabled" />
                    </div>
                </flux:card>

                @unless ($whatsapp_enabled)
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        {{ __('WhatsApp messages are disabled globally. Template settings can be updated, but no WhatsApp messages will be sent until this is enabled.') }}
                    </flux:callout>
                @endunless

                @foreach (collect($templateSettings)->groupBy('category') as $group => $settings)
                    <flux:card>
                        <div class="mb-4 flex items-center justify-between">
                            <flux:heading size="lg">{{ __($group) }}</flux:heading>
                            <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ trans_choice(':count type|:count types', count($settings), ['count' => count($settings)]) }}
                            </span>
                        </div>

                        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($settings as $code => $setting)
                                <div class="flex flex-col gap-3 py-4 md:flex-row md:items-center md:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $categoryLabels[$code] ?? \Illuminate\Support\Str::headline($code) }}</h3>
                                            @php
                                                $status = $setting['whatsapp_status'] ?? 'not_submitted';
                                                $statusClass = match ($status) {
                                                    'approved' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                                    'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                                    'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                                    default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
                                                };
                                            @endphp
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusClass }}">
                                                {{ \Illuminate\Support\Str::headline($status) }}
                                            </span>
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ !empty($templateEnabled[$code]) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' }}">
                                                {{ !empty($templateEnabled[$code]) ? __('Enabled') : __('Disabled') }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $setting['description'] ?? __('WhatsApp notification template.') }}</p>
                                        <code class="mt-2 inline-block rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $code }}</code>
                                    </div>
                                    <flux:switch wire:model.live="templateEnabled.{{ $code }}" />
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                @endforeach

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <x-icon name="check" class="mr-1 size-4" />
                    {{ __('Save Changes') }}
                </flux:button>
            </form>
        @endif

        @if ($tab === 'templates')
            <flux:card class="mt-6">
                <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('WhatsApp Templates') }}</flux:heading>
                            <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Edit WhatsApp template bodies, then submit them to Twilio for WhatsApp approval. Approved templates can be enabled for automated sending.') }}
                            </flux:text>
                        </div>
                        <div class="rounded-xl bg-white px-4 py-3 text-sm text-zinc-600 shadow-sm dark:bg-zinc-900/70 dark:text-zinc-300">
                            {{ __('Templates available') }}: <span class="font-semibold text-zinc-900 dark:text-white">{{ count($categories) }}</span>
                        </div>
                    </div>
                </div>

                <form wire:submit="saveTemplates" class="space-y-6">
                    @foreach ($categories as $category)
                        @php
                            $availableVariables = $categoryVariables[$category] ?? [];
                            $setting = $templateSettings[$category] ?? [];
                            $status = $setting['whatsapp_status'] ?? 'not_submitted';
                        @endphp
                        <div
                            x-data="{
                                body: @entangle('whatsappTemplates.'.$category),
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
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:heading size="md">{{ $categoryLabels[$category] ?? $category }}</flux:heading>
                                            @php
                                                $statusClass = match ($status) {
                                                    'approved' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                                    'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                                    'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                                    default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
                                                };
                                            @endphp
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusClass }}">
                                                {{ \Illuminate\Support\Str::headline($status) }}
                                            </span>
                                        </div>
                                        <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('Twilio Content SID') }}: <span class="font-mono">{{ $setting['twilio_content_sid'] ?? __('Not submitted') }}</span>
                                        </flux:text>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <select wire:model.blur="templateCategories.{{ $category }}" class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                            <option value="UTILITY">{{ __('Utility') }}</option>
                                            <option value="MARKETING">{{ __('Marketing') }}</option>
                                            <option value="AUTHENTICATION">{{ __('Authentication') }}</option>
                                        </select>
                                        <flux:button size="sm" type="button" variant="ghost" wire:click="refreshTemplateStatus('{{ $category }}')" wire:loading.attr="disabled">
                                            {{ __('Refresh') }}
                                        </flux:button>
                                        <flux:button size="sm" type="button" variant="primary" wire:click="submitTemplate('{{ $category }}')" wire:loading.attr="disabled">
                                            {{ __('Submit to Twilio') }}
                                        </flux:button>
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
                                        placeholder="{{ __('Type the WhatsApp template content here...') }}"
                                        class="w-full font-mono text-sm"
                                    />
                                    @if (!empty($setting['twilio_rejection_reason']))
                                        <flux:callout variant="danger" icon="exclamation-circle">
                                            {{ $setting['twilio_rejection_reason'] }}
                                        </flux:callout>
                                    @endif
                                </div>

                                <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/70">
                                    <flux:heading size="sm">{{ __('Quick Insert') }}</flux:heading>
                                    <flux:text class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Click a token below to place it in the template.') }}
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
    </flux:main>
</div>

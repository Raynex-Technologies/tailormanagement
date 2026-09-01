<div>
    <flux:main class="p-0">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Administration') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Email Setup') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if (session('error'))
            <flux:callout class="mt-4" variant="danger" icon="exclamation-circle">
                {{ session('error') }}
            </flux:callout>
        @endif

        <div class="mt-4">
            <flux:heading size="xl">{{ __('Email Setup') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('Manage sender details and customer-facing email templates for storefront checkout and invoices.') }}
            </flux:text>
        </div>

        <x-administration.settings-navigation active="email" />

        <div class="mt-6 flex flex-wrap gap-2 border-b border-zinc-200 dark:border-zinc-700">
            <button
                type="button"
                wire:click="$set('tab', 'smtp')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'smtp' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('SMTP Setup') }}
            </button>
            <button
                type="button"
                wire:click="$set('tab', 'templates')"
                class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === 'templates' ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
            >
                {{ __('Email Templates') }}
            </button>
        </div>

        @if ($tab === 'smtp')
            <flux:card class="mt-6">
                <div class="space-y-6">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/40 dark:text-zinc-300">
                        {{ __('Configure outgoing SMTP and optional incoming server credentials. Supported security modes include None, TLS, and SSL.') }}
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/40" data-email-delivery-controls>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <flux:heading size="lg">{{ __('Customer Email Delivery') }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Control automatic customer messages and deliberate invoice sends. New installations keep every option off until enabled here.') }}
                                </flux:text>
                            </div>
                            <flux:switch wire:model.live="email_sending_enabled" label="{{ __('Enable Email Sending') }}" />
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <label class="flex min-h-16 items-start gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                                <flux:switch wire:model="email_order_created_enabled" />
                                <span>
                                    <span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Send email when order is created') }}</span>
                                    <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Includes order details and the canonical invoice PDF.') }}</span>
                                </span>
                            </label>
                            <label class="flex min-h-16 items-start gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                                <flux:switch wire:model="email_payment_received_enabled" />
                                <span>
                                    <span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Send email when payment is received') }}</span>
                                    <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Includes the new payment, cumulative balance, and updated invoice PDF.') }}</span>
                                </span>
                            </label>
                        </div>

                        @if (! $email_sending_enabled)
                            <div class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                {{ __('Automatic customer emails and manual invoice sends are currently blocked. Test email remains available below.') }}
                            </div>
                        @endif
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <flux:heading size="lg">{{ __('Outgoing SMTP Server') }}</flux:heading>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <flux:select wire:model.blur="mail_mailer" label="{{ __('Mailer') }}">
                                <flux:select.option value="smtp">SMTP</flux:select.option>
                                <flux:select.option value="sendmail">Sendmail</flux:select.option>
                                <flux:select.option value="log">Log</flux:select.option>
                                <flux:select.option value="array">Array</flux:select.option>
                            </flux:select>
                            <flux:select wire:model.blur="mail_encryption" label="{{ __('Security') }}">
                                <flux:select.option value="none">{{ __('None') }}</flux:select.option>
                                <flux:select.option value="tls">TLS</flux:select.option>
                                <flux:select.option value="ssl">SSL</flux:select.option>
                            </flux:select>
                            <flux:input wire:model.blur="mail_host" label="{{ __('SMTP Host') }}" placeholder="smtp.example.com" />
                            <flux:input wire:model.blur="mail_port" type="number" min="1" max="65535" label="{{ __('SMTP Port') }}" placeholder="587" />
                            <flux:input wire:model.blur="mail_username" label="{{ __('SMTP Username') }}" />
                            <flux:input wire:model.blur="mail_password" type="password" autocomplete="new-password" label="{{ __('SMTP Password') }}" />
                            <flux:input wire:model.blur="mail_timeout" type="number" min="1" max="300" label="{{ __('Timeout (seconds)') }}" placeholder="30" />
                        </div>
                        <flux:text class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Leave password blank to keep the existing value.') }}
                        </flux:text>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <flux:heading size="lg">{{ __('Sender Identity') }}</flux:heading>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model.blur="email_from_name" label="{{ __('From Name') }}" />
                            <flux:input wire:model.blur="email_from_address" type="email" label="{{ __('From Email') }}" />
                            <flux:input wire:model.blur="email_reply_to" type="email" label="{{ __('Reply-To Email') }}" />
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <flux:heading size="lg">{{ __('Incoming Mail Server') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Optional IMAP/POP3 credentials for future inbound email processing.') }}
                                </flux:text>
                            </div>
                            <flux:switch wire:model.blur="incoming_enabled" />
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <flux:select wire:model.blur="incoming_protocol" label="{{ __('Protocol') }}">
                                <flux:select.option value="imap">IMAP</flux:select.option>
                                <flux:select.option value="pop3">POP3</flux:select.option>
                            </flux:select>
                            <flux:select wire:model.blur="incoming_encryption" label="{{ __('Security') }}">
                                <flux:select.option value="none">{{ __('None') }}</flux:select.option>
                                <flux:select.option value="tls">TLS</flux:select.option>
                                <flux:select.option value="ssl">SSL</flux:select.option>
                            </flux:select>
                            <flux:input wire:model.blur="incoming_host" label="{{ __('Incoming Host') }}" placeholder="imap.example.com" />
                            <flux:input wire:model.blur="incoming_port" type="number" min="1" max="65535" label="{{ __('Incoming Port') }}" placeholder="993" />
                            <flux:input wire:model.blur="incoming_username" label="{{ __('Incoming Username') }}" />
                            <flux:input wire:model.blur="incoming_password" type="password" autocomplete="new-password" label="{{ __('Incoming Password') }}" />
                        </div>
                        <flux:text class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Leave incoming password blank to keep the existing value.') }}
                        </flux:text>
                    </div>

                    <div class="flex justify-end">
                        <flux:button type="button" variant="primary" wire:click="saveSmtpSettings">
                            <x-icon name="check" class="mr-1 size-4" />
                            {{ __('Save SMTP Setup') }}
                        </flux:button>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/40" data-email-test-section>
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div class="max-w-xl">
                                <flux:heading size="lg">{{ __('Send Test Email') }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Uses the saved effective transport and is allowed even when customer email sending is disabled.') }}
                                </flux:text>
                            </div>
                            <div class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto">
                                <flux:input class="w-full sm:min-w-72" wire:model="test_email_to" type="email" label="{{ __('Test Recipient') }}" />
                                <flux:button class="sm:self-end" type="button" variant="outline" wire:click="sendTestEmail" wire:loading.attr="disabled" wire:target="sendTestEmail">
                                    <i class="fa-duotone fa-paper-plane mr-1.5" aria-hidden="true"></i>
                                    {{ __('Send Test') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card>
        @endif

        @if ($tab === 'templates')
            <flux:card class="mt-6 space-y-6">
                <form wire:submit="saveTemplates" class="space-y-6">
                    @foreach ($categories as $category)
                        @php($availableVariables = $categoryVariables[$category] ?? [])

                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <flux:heading size="lg">{{ $categoryLabels[$category] ?? $category }}</flux:heading>
                                <span class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                    {{ trans_choice(':count variable|:count variables', count($availableVariables), ['count' => count($availableVariables)]) }}
                                </span>
                            </div>

                            <div class="space-y-4">
                                <flux:input
                                    wire:model.blur="templates.{{ $category }}.subject"
                                    label="{{ __('Email Subject') }}"
                                />
                                <flux:textarea
                                    wire:model.blur="templates.{{ $category }}.body"
                                    label="{{ __('Email Body') }}"
                                    rows="7"
                                />
                            </div>

                            <div class="mt-4 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/50">
                                <flux:text class="block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ __('Available Variables') }}
                                </flux:text>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($availableVariables as $variable)
                                        @php($definition = $variableDefinitions[$variable] ?? null)
                                        <span
                                            class="rounded-lg border border-zinc-300 bg-zinc-50 px-2.5 py-1 text-xs font-mono text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                                            title="{{ $definition['description'] ?? '' }}"
                                        >
                                            {{ '{' . $variable . '}' }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">
                            <x-icon name="check" class="mr-1 size-4" />
                            {{ __('Save Email Templates') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        @endif
    </flux:main>
</div>

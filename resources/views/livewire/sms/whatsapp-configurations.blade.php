<div><flux:main class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><flux:heading size="xl">{{ __('Twilio WhatsApp') }}</flux:heading><flux:text>{{ __('Connect this branch to its Twilio WhatsApp sender.') }}</flux:text></div>
        <flux:button :href="route('whatsapp-templates.index')" wire:navigate>{{ __('Manage Templates') }}</flux:button>
    </div>
    @if(session('success'))<flux:callout variant="success">{{ session('success') }}</flux:callout>@endif
    @if(session('error'))<flux:callout variant="danger">{{ session('error') }}</flux:callout>@endif
    <flux:card class="space-y-4">
        <flux:heading>{{ __('Account and sender') }}</flux:heading>
        <flux:text>{{ __('Status: :status', ['status' => $integration->healthStatus()]) }}</flux:text>
        <flux:switch wire:model="enabled" label="{{ __('Enable WhatsApp notifications') }}"/>
        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="twilio_account_sid" label="{{ __('Twilio Account SID') }}" placeholder="AC..."/>
            <flux:input wire:model="twilio_from" label="{{ __('WhatsApp sender number') }}" placeholder="+255..." description="{{ __('Use the registered WhatsApp sender in international format, including +.') }}"/>
        </div>
        <flux:input wire:model="twilio_auth_token" type="password" autocomplete="new-password" label="{{ __('Twilio Auth Token') }}" description="{{ filled($integration->twilio_auth_token) ? __('A token is saved. Leave blank to keep it.') : __('Enter the Auth Token from your Twilio account.') }}"/>
        @can('sms-settings.update')
            <flux:text>{{ __('Test Connection saves these settings and immediately checks the account credentials with Twilio.') }}</flux:text>
            <div class="flex flex-wrap gap-2">
                <flux:button type="button" wire:click="save" wire:loading.attr="disabled">{{ __('Save Settings') }}</flux:button>
                <flux:button type="button" variant="primary" wire:click="testConnection" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="testConnection">{{ __('Test Connection') }}</span>
                    <span wire:loading wire:target="testConnection">{{ __('Testing connection...') }}</span>
                </flux:button>
            </div>
        @endcan
    </flux:card>
    <flux:card class="space-y-4">
        <flux:heading>{{ __('Incoming messages and delivery tracking') }}</flux:heading>
        <flux:text>{{ __('In Twilio Console, set the incoming-message webhook for your WhatsApp sender to this URL using HTTP POST. Delivery status callbacks are included automatically when sending.') }}</flux:text>
        <flux:input readonly :value="$webhookUrl" label="{{ __('Incoming-message URL') }}"/>
        <flux:text>{{ __('Use your public HTTPS application address. Webhook status becomes verified after a signed Twilio callback is received.') }}</flux:text>
        <flux:text>{{ __('Webhook status: :status', ['status' => $integration->twilioConfigured() ? ($integration->webhook_status ?: 'unverified') : 'not_configured']) }}</flux:text>
    </flux:card>
    <flux:card class="space-y-3"><flux:heading>{{ __('Recent messages') }}</flux:heading>
        @forelse($messages as $message)<div class="flex flex-wrap justify-between gap-2 border-b border-zinc-200 py-2 dark:border-zinc-700"><span>{{ $message->phone }}</span><span>{{ $message->direction }} / {{ $message->status }}</span><span>{{ $message->created_at }}</span></div>@empty<flux:text>{{ __('No messages yet.') }}</flux:text>@endforelse
    </flux:card>
</flux:main></div>

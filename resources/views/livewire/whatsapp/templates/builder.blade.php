<div><flux:main class="space-y-6">
    <div><flux:heading size="xl">{{ __('Twilio WhatsApp Template') }}</flux:heading><flux:text>{{ __('Save a text notification locally, validate it, then submit it through Twilio for WhatsApp approval.') }}</flux:text></div>
    @if(session('success'))<flux:callout variant="success">{{ session('success') }}</flux:callout>@endif
    @if(session('error'))<flux:callout variant="danger">{{ session('error') }}</flux:callout>@endif
    @if($template?->twilio_content_sid)<flux:callout>{{ __('Content already created in Twilio. To change the content, duplicate this template and choose a new name.') }}<br>{{ __('Approval: :status', ['status' => $template->twilio_status ?: 'Unknown']) }}</flux:callout>@endif
    @if($header_format !== 'NONE' || $footer || $buttons)<flux:callout variant="warning">{{ __('This draft contains formatting unsupported by the Twilio text editor. Remove it explicitly before validating.') }}<flux:button wire:click="useTextOnly" class="mt-3">{{ __('Remove header, footer, and buttons') }}</flux:button></flux:callout>@endif
    @if($template?->local_state === 'creation_unknown')<flux:card class="space-y-3"><flux:heading>{{ __('Reconcile content creation') }}</flux:heading><flux:text>{{ __('The creation response was uncertain. Find the matching template in Twilio Content Template Builder and enter its Content SID. We will verify its account and content before continuing.') }}</flux:text><flux:input wire:model="recovery_content_sid" label="{{ __('Content SID') }}" placeholder="HX..."/><flux:button wire:click="recoverContent">{{ __('Verify and recover content') }}</flux:button></flux:card>@endif
    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="space-y-5">
            <flux:card class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model="name" label="{{ __('Template name') }}"/>
                <flux:select wire:model="category" label="{{ __('Category') }}"><option>UTILITY</option><option>MARKETING</option></flux:select>
                <flux:select wire:model="language" label="{{ __('Language') }}"><option value="en">English</option><option value="en_US">English (US)</option><option value="sw">Swahili</option></flux:select>
            </flux:card>
            <flux:card class="space-y-4">
                <flux:textarea wire:model.live="body" rows="7" label="{{ __('Message body') }}"/>
                <flux:text>{{ __('Use numbered placeholders such as') }} @verbatim{{1}}@endverbatim{{ __('. Supply an example and an internal field name for each placeholder.') }}</flux:text>
                @foreach($examples as $n => $value)<div class="grid gap-3 sm:grid-cols-2" wire:key="variable-{{ $n }}"><flux:input wire:model="examples.{{$n}}" label="{{ __('Example for variable :n', ['n' => $n]) }}"/><flux:input wire:model="variable_mappings.{{$n}}" label="{{ __('Field for variable :n', ['n' => $n]) }}" placeholder="customer_name"/></div>@endforeach
                <flux:button wire:click="addVariable">{{ __('Add variable example') }}</flux:button>
            </flux:card>
            @can('sms-templates.update')<div class="flex flex-wrap gap-2">
                <flux:button wire:click="saveDraft" wire:loading.attr="disabled">{{ __('Save Draft') }}</flux:button>
                <flux:button wire:click="validateTemplate" wire:loading.attr="disabled">{{ __('Validate') }}</flux:button>
                <flux:button variant="primary" wire:click="submit" wire:loading.attr="disabled" wire:confirm="{{ __('Submit the saved, validated template through Twilio for WhatsApp approval?') }}">{{ __('Submit through Twilio') }}</flux:button>
            </div>@endcan
        </div>
        <div class="space-y-4">
            <flux:card><flux:heading>{{ __('Message preview') }}</flux:heading><div class="mt-3 whitespace-pre-wrap break-words rounded-xl bg-zinc-100 p-4 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ $preview }}</div></flux:card>
            @if($validation)<flux:card><flux:heading>{{ __('Template validation') }}</flux:heading>@foreach($validation['errors'] ?? [] as $error)<p class="mt-2 text-red-600 dark:text-red-400">{{ $error['message'] }}</p>@endforeach @foreach($validation['warnings'] ?? [] as $warning)<p class="mt-2 text-amber-600 dark:text-amber-400">{{ $warning['message'] }}</p>@endforeach</flux:card>@endif
        </div>
    </div>
</flux:main></div>

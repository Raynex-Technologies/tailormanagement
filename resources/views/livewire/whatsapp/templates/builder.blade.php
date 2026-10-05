<div><flux:main class="space-y-6">
    <div><flux:heading size="xl">{{ __('Twilio WhatsApp Template') }}</flux:heading><flux:text>{{ __('Save a text notification locally, validate it, then submit it through Twilio for WhatsApp approval.') }}</flux:text></div>
    @if(session('success'))<flux:callout variant="success">{{ session('success') }}</flux:callout>@endif
    @if(session('error'))<flux:callout variant="danger">{{ session('error') }}</flux:callout>@endif
    @if($errors->any())
        <flux:callout variant="danger" role="alert" data-template-errors>
            <flux:heading>{{ __('Please correct the highlighted fields') }}</flux:heading>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </flux:callout>
    @endif
    @if($template?->twilio_content_sid)<flux:callout>{{ __('Content already created in Twilio. To change the content, duplicate this template and choose a new name.') }}<br>{{ __('Approval: :status', ['status' => $template->twilio_status ?: 'Unknown']) }}</flux:callout>@endif
    @if($header_format !== 'NONE' || $footer || $buttons)<flux:callout variant="warning">{{ __('This draft contains formatting unsupported by the Twilio text editor. Remove it explicitly before validating.') }}<flux:button wire:click="useTextOnly" class="mt-3">{{ __('Remove header, footer, and buttons') }}</flux:button></flux:callout>@endif
    @if($template?->local_state === 'creation_unknown')<flux:card class="space-y-3"><flux:heading>{{ __('Reconcile content creation') }}</flux:heading><flux:text>{{ __('The creation response was uncertain. Find the matching template in Twilio Content Template Builder and enter its Content SID. We will verify its account and content before continuing.') }}</flux:text><flux:input wire:model="recovery_content_sid" label="{{ __('Content SID') }}" placeholder="HX..."/><flux:button wire:click="recoverContent">{{ __('Verify and recover content') }}</flux:button></flux:card>@endif
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="min-w-0 space-y-5">
            <flux:card class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model.blur="name" label="{{ __('Template name') }}"/>
                <flux:select wire:model.live="category" label="{{ __('Category') }}"><option>UTILITY</option><option>MARKETING</option></flux:select>
                <flux:select wire:model.live="language" label="{{ __('Language') }}"><option value="en">English</option><option value="en_US">English (US)</option><option value="sw">Swahili</option></flux:select>
            </flux:card>
            <flux:card class="space-y-4">
                <flux:textarea wire:model.live="body" rows="7" label="{{ __('Message body') }}"/>
                <flux:text>{{ __('Click a variable below Message preview to add it to the message. Examples are filled automatically from the SMS variable catalogue; you can edit them below. Actual notification values are used when sending.') }}</flux:text>
                @foreach($examples as $n => $value)
                    <div class="grid gap-3 sm:grid-cols-2" wire:key="variable-{{ $n }}">
                        <flux:select wire:model.live="variable_mappings.{{$n}}" label="{{ __('Variable for placeholder :n', ['n' => $n]) }}">
                            <option value="">{{ __('Choose a variable') }}</option>
                            @if(filled($variable_mappings[$n] ?? null) && is_string($variable_mappings[$n]) && !isset($variableOptions[$variable_mappings[$n]]))
                                <option value="{{ $variable_mappings[$n] }}" disabled>{{ __('Unsupported variable: :variable', ['variable' => $variable_mappings[$n]]) }}</option>
                            @endif
                            @foreach($variableOptions as $variable => $option)
                                <option value="{{ $variable }}">{{ $option['label'] }} — {{ '{'.$variable.'}' }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model.live="examples.{{$n}}" label="{{ __('Example for variable :n', ['n' => $n]) }}"/>
                    </div>
                @endforeach
            </flux:card>
            @can('sms-templates.update')<div class="flex flex-wrap gap-2">
                <flux:button variant="primary" wire:click="validateTemplate" wire:loading.attr="disabled">{{ __('Save and validate') }}</flux:button>
                @if($canSubmit)
                    <span wire:dirty.remove wire:target="name,category,language,body,examples,variable_mappings,header_format,header_text,footer,buttons">
                        <flux:button variant="primary" wire:click="submit" wire:loading.attr="disabled" wire:confirm="{{ __('Submit the saved, validated template through Twilio for WhatsApp approval?') }}">{{ __('Submit to Twilio') }}</flux:button>
                    </span>
                @endif
            </div>@endcan
        </div>
        <div class="space-y-4">
            <flux:card><flux:heading>{{ __('Message preview') }}</flux:heading><div class="mt-3 whitespace-pre-wrap break-words rounded-xl bg-zinc-100 p-4 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ $preview }}</div></flux:card>
            <flux:card class="space-y-3">
                <flux:heading>{{ __('SMS template variables') }}</flux:heading>
                <flux:text>{{ __('Click a variable to append its placeholder to the message. Choose variables available for the notification using this template.') }}</flux:text>
                <div class="flex flex-wrap gap-2">
                    @foreach($variableOptions as $variable => $option)
                        <button
                            type="button"
                            wire:key="insert-variable-{{ $variable }}"
                            wire:click="addVariable('{{ $variable }}')"
                            wire:loading.attr="disabled"
                            wire:target="addVariable"
                            title="{{ $option['description'] }}"
                            aria-label="{{ __('Insert :variable', ['variable' => $option['label']]) }}"
                            class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-left text-zinc-900 transition hover:border-[var(--tm-accent)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--tm-accent)] disabled:cursor-wait disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <span class="block text-xs font-medium">{{ $option['label'] }}</span>
                            <span class="block break-all font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ '{'.$variable.'}' }}</span>
                        </button>
                    @endforeach
                </div>
                <flux:error name="variableOptions"/>
            </flux:card>
            @if($validation['warnings'] ?? [])<flux:callout variant="warning"><flux:heading>{{ __('Template warnings') }}</flux:heading>@foreach($validation['warnings'] as $warning)<p class="mt-2">{{ $warning['message'] }}</p>@endforeach</flux:callout>@endif
        </div>
    </div>
</flux:main></div>

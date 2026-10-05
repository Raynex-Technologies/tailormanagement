<div>
    <flux:main class="space-y-6 p-0">
        <section class="overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:p-6" style="background: linear-gradient(135deg, var(--tm-hero) 0%, color-mix(in srgb, var(--tm-hero) 88%, #ffffff 12%) 100%);" data-theme-hero data-whatsapp-templates-header>
            <flux:breadcrumbs class="mb-5 text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item class="!text-white">{{ __('WhatsApp Templates') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <flux:heading size="xl" class="!text-white">{{ __('WhatsApp Templates') }}</flux:heading>
                    <p class="mt-1 text-sm text-white/70">{{ __('Create drafts, submit them to Twilio and track WhatsApp approval.') }}</p>
                </div>
                @can('sms-templates.update')
                    <div class="flex flex-wrap gap-2">
                        <flux:button variant="outline" icon="arrow-path" wire:click="sync" wire:loading.attr="disabled" class="!border-white/25 !bg-white/10 !text-white hover:!bg-white/20">{{ __('Refresh Approvals') }}</flux:button>
                        <flux:button variant="primary" icon="plus" :href="route('whatsapp-templates.create')" wire:navigate>{{ __('New Template') }}</flux:button>
                    </div>
                @endcan
            </div>
        </section>

        @if(session('success'))<flux:callout variant="success">{{ session('success') }}</flux:callout>@endif
        @if(session('error'))<flux:callout variant="danger">{{ session('error') }}</flux:callout>@endif

        <section class="space-y-4" aria-label="{{ __('Template list') }}">
            <div class="flex items-center justify-between gap-3">
                <flux:heading>{{ __('All templates') }}</flux:heading>
                <flux:text>{{ trans_choice(':count template|:count templates', $templates->total(), ['count' => $templates->total()]) }}</flux:text>
            </div>
            @forelse($templates as $t)
                @php
                    $localColor = match ($t->local_state) {
                        'ready_to_submit' => 'green',
                        'validation_failed' => 'red',
                        'creation_unknown', 'submitting' => 'amber',
                        default => 'zinc',
                    };
                    $localLabel = match ($t->local_state) {
                        'ready_to_submit' => __('Ready to submit'),
                        'validation_failed' => __('Needs corrections'),
                        'creation_unknown' => __('Needs reconciliation'),
                        default => (string) str($t->local_state ?: 'draft')->headline(),
                    };
                    $approvalColor = match ($t->twilio_status) {
                        'APPROVED' => 'green',
                        'PENDING', 'UNSUBMITTED' => 'amber',
                        'REJECTED', 'DISABLED' => 'red',
                        default => 'zinc',
                    };
                @endphp
                <flux:card wire:key="template-{{ $t->id }}">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0 flex-1 space-y-2">
                            <flux:heading class="break-all">{{ $t->name }}</flux:heading>
                            <flux:text>{{ $t->category }} &middot; {{ $t->language }} &middot; {{ __('Text template') }}</flux:text>
                            <div class="flex flex-wrap gap-2" aria-label="{{ __('Template status') }}">
                                <flux:badge size="sm" :color="$localColor">{{ $localLabel }}</flux:badge>
                                <flux:badge size="sm" :color="$approvalColor">{{ $t->twilio_status ? __('Twilio: :status', ['status' => (string) str($t->twilio_status)->lower()->headline()]) : ($t->meta_template_id ? __('Historical Meta template') : __('Not submitted')) }}</flux:badge>
                            </div>
                            @if($t->rejection_reason)<p class="text-sm text-red-600 dark:text-red-400">{{ $t->rejection_reason }}</p>@endif
                            @if($t->hasLocalDivergence())<p class="text-sm text-amber-600 dark:text-amber-400">{{ __('Local changes not submitted') }}</p>@endif
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-2" data-template-actions>
                            <flux:button size="sm" icon="pencil-square" :href="route('whatsapp-templates.edit', ['template' => $t->id])" wire:navigate>{{ __('View / Edit') }}</flux:button>
                            @can('sms-templates.update')
                                <flux:button size="sm" icon="document-duplicate" wire:click="duplicate({{ $t->id }})" wire:loading.attr="disabled">{{ __('Duplicate') }}</flux:button>
                                <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $t->id }})" wire:loading.attr="disabled" wire:confirm="{{ __('Delete this template?') }}">{{ __('Delete') }}</flux:button>
                            @endcan
                        </div>
                    </div>
                </flux:card>
            @empty
                <flux:card class="py-10 text-center"><flux:heading>{{ __('No templates yet') }}</flux:heading><flux:text class="mt-2">{{ __('Create your first template, then save and validate it before submitting to Twilio.') }}</flux:text></flux:card>
            @endforelse
        </section>
        {{ $templates->links() }}
    </flux:main>
</div>

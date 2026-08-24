<flux:main class="mx-auto max-w-5xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('customers.index') }}" wire:navigate>{{ __('Customers') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item href="{{ route('customers.show', $customer) }}" wire:navigate>{{ $customer->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Measurement Revision :revision', ['revision' => $measurementProfile->revision]) }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl">{{ __('Measurement Revision :revision', ['revision' => $measurementProfile->revision]) }}</flux:heading>
                @if ($measurementProfile->is_current)
                    <flux:badge color="green">{{ __('Current') }}</flux:badge>
                @else
                    <flux:badge color="zinc">{{ __('Historical') }}</flux:badge>
                @endif
            </div>
            <flux:text class="mt-1 text-zinc-500">{{ $customer->name }} · {{ $customer->code }}</flux:text>
        </div>
        <flux:button type="button" variant="ghost" :href="route('customers.show', $customer)" wire:navigate>
            {{ __('Back to Customer') }}
        </flux:button>
    </div>

    <flux:card>
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-zinc-500">{{ __('Measured on') }}</dt>
                <dd class="mt-1 font-medium">{{ $measurementProfile->measured_at?->format('M d, Y') ?: __('Not recorded') }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500">{{ __('Recorded by') }}</dt>
                <dd class="mt-1 font-medium">{{ $measurementProfile->recordedBy?->name ?: __('Former or unavailable user') }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500">{{ __('Revision') }}</dt>
                <dd class="mt-1 font-medium">{{ $measurementProfile->revision }}</dd>
            </div>
        </dl>
        @if ($measurementProfile->notes)
            <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-white/10">
                <div class="text-sm text-zinc-500">{{ __('Notes') }}</div>
                <p class="mt-1 whitespace-pre-wrap text-sm">{{ $measurementProfile->notes }}</p>
            </div>
        @endif
    </flux:card>

    <flux:card>
        <flux:heading size="lg">{{ __('Saved Values') }}</flux:heading>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($measurementProfile->orderedValues() as $value)
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-medium">{{ $value->field_label_snapshot ?: $value->field?->name ?: __('Archived measurement') }}</div>
                            <div class="mt-1 font-mono text-xs text-zinc-500">{{ $value->field_code_snapshot ?: $value->field?->code ?: '—' }}</div>
                        </div>
                        @if (! ($value->field?->is_active ?? false))
                            <flux:badge color="zinc" size="sm">{{ __('Archived') }}</flux:badge>
                        @endif
                    </div>
                    <div class="mt-3 text-xl font-semibold tabular-nums">{{ $value->value }} <span class="text-sm font-normal text-zinc-500">{{ $value->unit }}</span></div>
                </div>
            @endforeach
        </div>
    </flux:card>
</flux:main>

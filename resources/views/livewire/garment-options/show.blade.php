<div class="flex min-w-0 flex-col gap-6">
    <x-orders.workspace-header class="!mb-0" :title="$garmentType->name" :subtitle="__('Manage this garment type’s measurements, customization choices and commercial offerings.')">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('order-catalog.index')" wire:navigate>{{ __('Order Catalog') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('order-catalog.garment-types.index')" wire:navigate>{{ __('Garment Types') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $garmentType->name }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>
        @can('garment-options.manage')
            <x-slot:actions>
                <flux:button variant="primary" icon="pencil-square" wire:click="edit({{ $garmentType->id }})">{{ __('Edit Garment Type') }}</flux:button>
                <flux:button variant="outline" wire:click="toggleActive({{ $garmentType->id }})">{{ $garmentType->is_active ? __('Archive') : __('Reactivate') }}</flux:button>
            </x-slot:actions>
        @endcan
    </x-orders.workspace-header>

    <x-orders.catalog-navigation active="garment-types" />
    @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif

    <div class="flex flex-wrap items-center gap-3">
        <flux:badge :color="$garmentType->is_active ? 'green' : 'zinc'">{{ $garmentType->is_active ? __('Active') : __('Archived') }}</flux:badge>
        <flux:text>{{ $garmentType->gender_scope ? __(ucfirst($garmentType->gender_scope)) : __('Audience not specified') }}</flux:text>
        @if (! $garmentType->is_active)<flux:text>{{ __('Existing relationships are retained. Reactivate this type before using it for new catalogue items.') }}</flux:text>@endif
    </div>

    <nav class="flex flex-wrap gap-2 border-b border-zinc-200 pb-3 dark:border-white/10" aria-label="{{ __('Garment type sections') }}">
        @foreach (['overview' => __('Overview'), 'measurements' => __('Measurements'), 'customization' => __('Customization'), 'items' => __('Catalogue Items')] as $key => $label)
            @if ($key !== 'items' || auth()->user()->can('order_catalog.view'))
                <button type="button" wire:click="setTab('{{ $key }}')" @if($tab === $key) aria-current="page" @endif class="rounded-lg px-3 py-2 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--tm-accent)] {{ $tab === $key ? 'tm-active' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5' }}">{{ $label }}</button>
            @endif
        @endforeach
    </nav>

    @if ($tab === 'overview')
        <section class="grid gap-6 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]" aria-label="{{ __('Garment type overview') }}">
            <div>
                <flux:heading size="lg">{{ __('About this garment type') }}</flux:heading>
                <flux:text class="mt-2 whitespace-pre-line">{{ $garmentType->description ?: __('Add a description to help staff choose the right tailoring configuration.') }}</flux:text>
                <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-zinc-500">{{ __('Name') }}</dt><dd class="mt-1 font-medium">{{ $garmentType->name }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Sort order') }}</dt><dd class="mt-1 font-medium">{{ $garmentType->sort_order }}</dd></div>
                </dl>
            </div>
            @if ($garmentType->image_url)<img src="{{ $garmentType->image_url }}" alt="{{ $garmentType->image_alt ?: $garmentType->name }}" class="max-h-56 w-full rounded-2xl object-cover">@endif
        </section>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([['measurements', 'Measurements', $garmentType->measurement_fields_count], ['customization', 'Customization Groups', $garmentType->option_groups_count], ['items', 'Catalogue Items', $garmentType->catalog_items_count]] as [$key, $label, $count])
                @if ($count !== null)
                    <button type="button" wire:click="setTab('{{ $key }}')" class="rounded-2xl border border-zinc-200 bg-white p-5 text-left shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--tm-accent)] dark:border-white/10 dark:bg-zinc-900">
                        <span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ __($label) }}</span><strong class="mt-2 block text-2xl">{{ $count }}</strong>
                    </button>
                @endif
            @endforeach
        </div>
    @elseif ($tab === 'measurements')
        <section class="space-y-4" aria-label="{{ __('Measurements') }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div><flux:heading size="lg">{{ __('Measurement template') }}</flux:heading><flux:text class="mt-1">{{ __('Required flags guide measurement entry. Missing values do not block initial order saving; additional measurements can be added.') }}</flux:text></div>
                @can('measurement_fields.manage')<flux:button variant="primary" wire:click="manageMeasurements" icon="adjustments-horizontal">{{ __('Manage Measurements') }}</flux:button>@endcan
            </div>
            @forelse ($measurements as $field)
                <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto_auto] sm:items-center dark:border-white/10">
                    <div class="min-w-0"><p class="font-medium">{{ $field->name }}</p><p class="break-all text-xs text-zinc-500">{{ $field->code }}</p></div>
                    <span class="text-sm">{{ __('Default unit') }}: {{ $field->default_unit }}</span>
                    <span class="text-sm">{{ $field->pivot->is_required ? __('Required Measurement') : __('Optional') }} · {{ __('Display Order') }}: {{ $field->pivot->sort_order }}</span>
                    <flux:badge :color="$field->is_active ? 'green' : 'zinc'">{{ $field->is_active ? __('Active') : __('Archived') }}</flux:badge>
                </div>
            @empty
                <flux:card><flux:heading>{{ __('No measurements configured') }}</flux:heading><flux:text class="mt-2">{{ __('Choose which measurements should normally be recorded for :name.', ['name' => $garmentType->name]) }}</flux:text></flux:card>
            @endforelse
        </section>
    @elseif ($tab === 'customization')
        <section class="space-y-4" aria-label="{{ __('Customization') }}">
            <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="lg">{{ __('Customization groups and choices') }}</flux:heading>
                @can('garment-options.manage')<flux:button variant="primary" icon="plus" :href="route('admin.garment-option-groups.index', ['garment_type' => $garmentType->id])" wire:navigate>{{ __('Add Customization Group') }}</flux:button>@endcan
            </div>
            @forelse ($groups as $group)
                <flux:card>
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><flux:heading>{{ $group->name }}</flux:heading><flux:text>{{ $group->is_active ? __('Active') : __('Archived') }} · {{ $group->is_required ? __('Required') : __('Optional') }}</flux:text></div>
                        <div class="flex flex-wrap gap-2">
                            @can('garment-options.manage')<flux:button size="sm" variant="ghost" :href="route('admin.garment-option-groups.index', ['garment_type' => $garmentType->id, 'edit_group' => $group->id])" wire:navigate>{{ __('Edit Group') }}</flux:button>@endcan
                            <flux:button size="sm" variant="ghost" :href="route('admin.garment-options.index', ['garment_type' => $garmentType->id, 'group' => $group->id])" wire:navigate>{{ __('Manage Options') }}</flux:button>
                        </div>
                    </div>
                    <ul class="mt-4 flex flex-wrap gap-2">@forelse ($group->options as $option)<li class="rounded-lg bg-zinc-100 px-3 py-1.5 text-sm dark:bg-white/5">{{ $option->label }}@if(! $option->is_active) <span class="text-zinc-500">({{ __('Archived') }})</span>@endif</li>@empty<li class="text-sm text-zinc-500">{{ __('No choices in this group yet.') }}</li>@endforelse</ul>
                </flux:card>
            @empty
                <flux:card><flux:heading>{{ __('No customization options yet') }}</flux:heading><flux:text class="mt-2">{{ __('Add tailoring choices such as lapel style, buttons, pockets, or lining.') }}</flux:text></flux:card>
            @endforelse
        </section>
    @elseif ($tab === 'items')
        <section class="space-y-4" aria-label="{{ __('Catalogue Items') }}">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><flux:heading size="lg">{{ __('Commercial offerings') }}</flux:heading><flux:text>{{ __('Catalogue items available to your branch that use this garment type.') }}</flux:text></div>
                @if ($garmentType->is_active)@can('order_catalog.items.manage')<flux:button variant="primary" icon="plus" :href="route('order-catalog.items.create', ['garment_type' => $garmentType->id])" wire:navigate>{{ __('Add Catalogue Item') }}</flux:button>@endcan @endif
            </div>
            @forelse ($items as $item)
                <flux:card>
                    <div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0"><flux:heading>{{ $item->name }}</flux:heading><flux:text>{{ $item->code }}</flux:text></div><flux:badge :color="$item->archived_at ? 'zinc' : 'green'">{{ $item->archived_at ? __('Archived') : __('Active') }}</flux:badge></div>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3"><div><dt class="text-zinc-500">{{ __('Default Price') }}</dt><dd class="mt-1 font-medium">{{ money_currency($item->default_selling_price) }}</dd></div><div><dt class="text-zinc-500">{{ __('Measurements') }}</dt><dd class="mt-1">{{ $item->requires_measurements ? __('Enabled') : __('Not required') }}</dd></div><div><dt class="text-zinc-500">{{ __('Branch Availability') }}</dt><dd class="mt-1">{{ $item->available_all_branches ? __('All Branches') : $item->branches->pluck('name')->join(', ') }}</dd></div></dl>
                    @can('order_catalog.items.manage')
                        @if(auth()->user()->isGlobalAdmin() || (! $item->available_all_branches && $item->branches->pluck('id')->all() === [(int) auth()->user()->branch_id]))<flux:button class="mt-4" size="sm" variant="ghost" :href="route('order-catalog.items.edit', $item)" wire:navigate>{{ __('Edit Catalogue Item') }}</flux:button>@endif
                    @endcan
                </flux:card>
            @empty
                <flux:card><flux:heading>{{ __('No catalogue items use :name in your available catalogue yet.', ['name' => $garmentType->name]) }}</flux:heading><flux:text class="mt-2">{{ __('Create a commercial offering that can be added to orders.') }}</flux:text></flux:card>
            @endforelse
        </section>
    @endif

    @if ($managingMeasurements)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="manage-measurements-title" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.set('managingMeasurements', false)">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-t-2xl bg-white p-5 sm:rounded-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between gap-3"><flux:heading id="manage-measurements-title" size="lg">{{ __('Manage Measurements') }}</flux:heading><flux:button variant="ghost" icon="x-mark" aria-label="{{ __('Close') }}" wire:click="$set('managingMeasurements', false)" /></div>
                <flux:text class="mt-2">{{ __('Choose measurements, mark those normally required, and set their display order. Archived links can be retained or removed.') }}</flux:text>
                <form wire:submit="saveMeasurements" class="mt-5 space-y-4">
                    @error('measurementSettings')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @foreach ($selectedFields as $field)
                        <div wire:key="measurement-setting-{{ $field->id }}" class="grid gap-3 border-b border-zinc-200 pb-4 sm:grid-cols-[minmax(0,1fr)_auto_8rem] sm:items-center dark:border-white/10">
                            <flux:checkbox wire:model.live="measurementSettings.{{ $field->id }}.selected" :label="$field->name.(! $field->is_active ? ' ('.__('Archived').')' : '')" />
                            <flux:checkbox wire:model="measurementSettings.{{ $field->id }}.required" :label="__('Required')" :disabled="!($measurementSettings[$field->id]['selected'] ?? false)" />
                            <flux:input type="number" min="0" max="9999" wire:model="measurementSettings.{{ $field->id }}.sort_order" :label="__('Display Order')" :disabled="!($measurementSettings[$field->id]['selected'] ?? false)" />
                        </div>
                    @endforeach
                    <flux:input wire:model.live.debounce.300ms="measurementSearch" icon="magnifying-glass" :label="__('Search active measurements')" />
                    <div class="flex flex-wrap gap-2">@forelse ($availableFields as $field)<flux:button size="sm" variant="outline" icon="plus" wire:click="selectMeasurement({{ $field->id }})">{{ $field->name }} ({{ $field->default_unit }})</flux:button>@empty<flux:text>{{ __('No matching active measurements to add.') }}</flux:text>@endforelse</div>
                    <div class="flex flex-wrap justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-white/10"><flux:button variant="ghost" wire:click="$set('managingMeasurements', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save Measurements') }}</flux:button></div>
                </form>
            </div>
        </div>
    @endif
    @include('livewire.garment-options.category-editor')
</div>

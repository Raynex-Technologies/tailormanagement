<flux:main class="flex flex-col gap-6">
    <x-orders.workspace-header class="!mb-0" :title="__('Garment Types')" :subtitle="__('Define reusable garment types, then connect measurements, customizations and commercial offerings.')">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs class="text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item :href="route('order-catalog.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Order Catalog') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item class="!text-white">{{ __('Garment Types') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="outline" icon="ellipsis-vertical" class="!border-white/25 !bg-white/10 !text-white hover:!bg-white/20" aria-label="{{ __('Garment type actions') }}" />
                <flux:menu class="w-64">
                    <flux:menu.item :href="route('admin.garment-options.index')" wire:navigate icon="swatch">{{ __('Customization Choices') }}</flux:menu.item>
                    <flux:menu.item :href="route('admin.garment-option-groups.index')" wire:navigate icon="adjustments-horizontal">{{ __('Customization Sections') }}</flux:menu.item>
                    @can('garment-options.manage')
                        <flux:menu.separator />
                        <flux:menu.item wire:click="create" icon="plus">{{ __('New Garment Type') }}</flux:menu.item>
                    @endcan
                </flux:menu>
            </flux:dropdown>
        </x-slot:actions>
    </x-orders.workspace-header>

    <x-orders.catalog-navigation active="garment-types" />
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <flux:card>
        <div class="grid gap-3 sm:grid-cols-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search garment types') }}" />
            <flux:select wire:model.live="status">
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Archived') }}</flux:select.option>
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($categories as $category)
            <article wire:key="garment-category-{{ $category->id }}" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-zinc-900">
                <div class="h-28 bg-zinc-100 dark:bg-white/5">
                    @if ($category->image_url)
                        <img src="{{ $category->image_url }}" alt="{{ $category->image_alt ?: $category->name }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-zinc-400"><i class="fa-duotone fa-shirt text-4xl"></i></div>
                    @endif
                </div>
                <div class="space-y-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ $category->name }}</h2>
                            <p class="mt-1 line-clamp-2 text-sm text-zinc-500">{{ $category->description ?: __('No description') }}</p>
                        </div>
                        <flux:badge size="sm" color="{{ $category->is_active ? 'green' : 'zinc' }}">{{ $category->is_active ? __('Active') : __('Archived') }}</flux:badge>
                    </div>
                    <div class="grid grid-cols-2 gap-3 rounded-xl bg-zinc-50 p-3 text-sm dark:bg-white/5">
                        <div><span class="block text-xs text-zinc-500">{{ __('Customization Groups') }}</span><strong>{{ $category->option_groups_count }}</strong></div>
                        <div><span class="block text-xs text-zinc-500">{{ __('Measurements') }}</span><strong>{{ $category->measurement_fields_count }}</strong></div>
                    </div>
                    @can('order_catalog.view')<p class="text-sm text-zinc-500">{{ __('Catalog Items') }}: <strong>{{ $category->catalog_items_count }}</strong></p>@endcan
                    @if ($category->gender_scope)<p class="text-sm text-zinc-500">{{ __(ucfirst($category->gender_scope)) }}</p>@endif
                    <div class="flex flex-wrap justify-end gap-2">
                        <flux:button size="xs" variant="ghost" :href="route('order-catalog.garment-types.show', $category)" wire:navigate>{{ __('Open Workspace') }}</flux:button>
                        @can('garment-options.manage')
                            <flux:button size="xs" variant="ghost" wire:click="edit({{ $category->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="xs" variant="ghost" wire:click="toggleActive({{ $category->id }})">{{ $category->is_active ? __('Archive') : __('Activate') }}</flux:button>
                        @endcan
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 py-16 text-center text-zinc-500">{{ __('No garment types match these filters.') }}</div>
        @endforelse
    </div>

    @include('livewire.garment-options.category-editor')
</flux:main>
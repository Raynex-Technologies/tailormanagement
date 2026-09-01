<div class="space-y-5">
    <section class="rounded-2xl p-5 text-white shadow-lg sm:p-6" style="background: linear-gradient(135deg, var(--tailorpro-primary) 0%, color-mix(in srgb, var(--tailorpro-primary) 88%, #ffffff 12%) 100%);" data-orders-workspace-header>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:breadcrumbs class="mb-5 text-white/70">
                    <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                    <flux:breadcrumbs.item :href="route('orders.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Orders') }}</flux:breadcrumbs.item>
                    <flux:breadcrumbs.item class="!text-white">{{ __('Order Catalog') }}</flux:breadcrumbs.item>
                </flux:breadcrumbs>
                <h1 class="text-2xl font-semibold">{{ __('Order Catalog') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-white/65">{{ __('Maintain reusable garments, services and commercial packages for faster order entry.') }}</p>
            </div>
            @if ($tab === 'items' && $canManageItems)
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    @can('garment-options.view')
                        <flux:button :href="route('admin.garment-options.index')" wire:navigate variant="ghost" icon="adjustments-horizontal" class="w-full border border-white/25 !text-white hover:!bg-white/10 sm:w-auto">
                            {{ __('Garment Customizations') }}
                        </flux:button>
                    @endcan
                    <flux:button :href="route('order-catalog.items.create')" wire:navigate variant="primary" icon="plus" class="w-full sm:w-auto">{{ __('New Catalog Item') }}</flux:button>
                </div>
            @elseif ($tab === 'packages' && $canManagePackages)
                <flux:button :href="route('order-catalog.packages.create')" wire:navigate variant="primary" icon="plus" class="w-full sm:w-auto">{{ __('New Package') }}</flux:button>
            @elseif ($tab === 'measurements' && $canManageMeasurements)
                <flux:button :href="route('order-catalog.measurements.create')" wire:navigate variant="primary" icon="plus" class="w-full sm:w-auto">{{ __('Add Measurement') }}</flux:button>
            @endif
        </div>
    </section>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <div class="flex rounded-xl border border-zinc-200 bg-white p-1 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e]" role="tablist" aria-label="{{ __('Order Catalog sections') }}">
        <button wire:click="setTab('items')" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition {{ $tab === 'items' ? 'bg-lime-400 text-[#1e1f2e] shadow-sm' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5' }}" role="tab" aria-selected="{{ $tab === 'items' ? 'true' : 'false' }}">
            <i class="fa-duotone fa-shirt mr-2"></i>{{ __('Catalog Items') }}
        </button>
        <button wire:click="setTab('packages')" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition {{ $tab === 'packages' ? 'bg-lime-400 text-[#1e1f2e] shadow-sm' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5' }}" role="tab" aria-selected="{{ $tab === 'packages' ? 'true' : 'false' }}">
            <i class="fa-duotone fa-box-open-full mr-2"></i>{{ __('Packages') }}
        </button>
        <button wire:click="setTab('measurements')" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition {{ $tab === 'measurements' ? 'bg-lime-400 text-[#1e1f2e] shadow-sm' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5' }}" role="tab" aria-selected="{{ $tab === 'measurements' ? 'true' : 'false' }}">
            <i class="fa-duotone fa-ruler-combined mr-2"></i>{{ __('Measurements') }}
        </button>
    </div>

    <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e]">
        <div class="grid gap-3 md:grid-cols-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ $tab === 'items' ? __('Search name or item code') : ($tab === 'packages' ? __('Search name or package code') : __('Search measurement name or code')) }}" />
            @if ($tab === 'items')
                <flux:select wire:model.live="typeFilter" aria-label="{{ __('Filter by type') }}">
                    <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                    @foreach ($types as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}s</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:select wire:model.live="statusFilter" aria-label="{{ __('Filter by status') }}">
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="archived">{{ __('Archived') }}</flux:select.option>
            </flux:select>
            @if ($tab === 'measurements')
                <flux:select wire:model.live="unitFilter" aria-label="{{ __('Filter by unit') }}">
                    <flux:select.option value="">{{ __('All units') }}</flux:select.option>
                    @foreach ($measurementUnits as $unit)<flux:select.option value="{{ $unit }}">{{ strtoupper($unit) }}</flux:select.option>@endforeach
                </flux:select>
                <flux:select wire:model.live="categoryFilter" aria-label="{{ __('Filter by garment category') }}">
                    <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                    @foreach ($garmentCategories as $category)<flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>@endforeach
                </flux:select>
            @elseif ($branches->count() > 1)
                <flux:select wire:model.live="branchFilter" aria-label="{{ __('Filter by branch') }}">
                    <flux:select.option value="">{{ __('All permitted branches') }}</flux:select.option>
                    @foreach ($branches as $branch)
                        <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>
    </section>

    @if ($tab === 'items')
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($items as $item)
                <article class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#1e1f2e]">
                    <div class="flex gap-4 p-4">
                        <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-zinc-100 dark:bg-white/5">
                            @if ($item->image_url)
                                <img src="{{ $item->image_url }}" alt="" class="size-full object-cover">
                            @else
                                <i class="fa-duotone fa-shirt text-2xl text-zinc-400"></i>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-zinc-500">{{ $item->code }}</p>
                                    <h2 class="truncate font-semibold text-zinc-900 dark:text-white">{{ $item->name }}</h2>
                                </div>
                                <flux:badge size="sm" color="{{ $item->archived_at ? 'zinc' : 'lime' }}">{{ $item->archived_at ? __('Archived') : __('Active') }}</flux:badge>
                            </div>
                            <p class="mt-2 text-lg font-semibold text-zinc-900 dark:text-white">{{ money_currency($item->default_selling_price, config('app.currency', 'TZS')) }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 border-y border-zinc-100 px-4 py-3 text-xs dark:border-white/10">
                        <div><span class="block text-zinc-400">{{ __('Type') }}</span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $item->type->label() }}</span></div>
                        <div><span class="block text-zinc-400">{{ __('Quantity') }}</span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $item->quantity_behavior->label() }}</span></div>
                        <div><span class="block text-zinc-400">{{ __('Measurements') }}</span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $item->requires_measurements ? __('Required') : __('Not required') }}</span></div>
                        <div><span class="block text-zinc-400">{{ __('Availability') }}</span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $item->available_all_branches ? __('All branches') : trans_choice(':count branch|:count branches', $item->branches->count(), ['count' => $item->branches->count()]) }}</span></div>
                    </div>
                    @if ($canManageItems)
                        <div class="flex items-center justify-end gap-2 p-3">
                            <flux:button size="sm" variant="ghost" :href="route('order-catalog.items.edit', $item)" wire:navigate icon="pencil-square">{{ __('Edit') }}</flux:button>
                            @if ($item->archived_at)
                                <flux:button size="sm" variant="ghost" wire:click="reactivateItem({{ $item->id }})" wire:confirm="{{ __('Reactivate this catalog item?') }}">{{ __('Reactivate') }}</flux:button>
                            @else
                                <flux:button size="sm" variant="ghost" wire:click="archiveItem({{ $item->id }})" wire:confirm="{{ __('Archive this catalog item? Existing orders and packages remain intact.') }}">{{ __('Archive') }}</flux:button>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-14 text-center dark:border-white/15 dark:bg-[#1e1f2e]">
                    <i class="fa-duotone fa-shirt text-4xl text-zinc-300"></i>
                    <h2 class="mt-4 font-semibold text-zinc-900 dark:text-white">{{ __('No catalog items yet') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Add frequently sold garments or services to make order entry faster.') }}</p>
                </div>
            @endforelse
        </div>
        @if ($items->hasPages()) <div>{{ $items->links() }}</div> @endif
    @elseif ($tab === 'packages')
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($packages as $package)
                @php($summary = $package->pricing_summary)
                <article class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#1e1f2e]">
                    <div class="relative h-32 bg-zinc-100 dark:bg-white/5">
                        @if ($package->cover_image_url)<img src="{{ $package->cover_image_url }}" alt="" class="size-full object-cover">@else<div class="flex size-full items-center justify-center"><i class="fa-duotone fa-box-open-full text-4xl text-zinc-300"></i></div>@endif
                        <flux:badge class="absolute right-3 top-3" size="sm" color="{{ $package->archived_at ? 'zinc' : 'lime' }}">{{ $package->archived_at ? __('Archived') : __('Active') }}</flux:badge>
                    </div>
                    <div class="p-4">
                        <p class="text-xs font-medium text-zinc-500">{{ $package->code }} · {{ __('Revision :revision', ['revision' => $package->revision]) }}</p>
                        <h2 class="mt-1 font-semibold text-zinc-900 dark:text-white">{{ $package->name }}</h2>
                        <p class="mt-1 text-xs text-zinc-500">{{ trans_choice(':count component|:count components', $package->items->count(), ['count' => $package->items->count()]) }} · {{ $package->available_all_branches ? __('All branches') : trans_choice(':count branch|:count branches', $package->branches->count(), ['count' => $package->branches->count()]) }}</p>
                        <div class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-zinc-50 p-3 text-xs dark:bg-white/5">
                            <div><span class="block text-zinc-400">{{ __('Standard') }}</span><strong>{{ money_currency($summary['standard_value']) }}</strong></div>
                            <div><span class="block text-zinc-400">{{ __('Package') }}</span><strong>{{ money_currency($summary['package_price']) }}</strong></div>
                            <div><span class="block text-zinc-400">{{ (float) $summary['difference'] >= 0 ? __('Savings') : __('Difference') }}</span><strong class="{{ (float) $summary['difference'] < 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ money_currency(abs((float) $summary['difference'])) }}</strong></div>
                        </div>
                    </div>
                    @if ($canManagePackages)
                        <div class="flex items-center justify-end gap-2 border-t border-zinc-100 p-3 dark:border-white/10">
                            <flux:button size="sm" variant="ghost" :href="route('order-catalog.packages.edit', $package)" wire:navigate icon="pencil-square">{{ __('Edit') }}</flux:button>
                            @if ($package->archived_at)
                                <flux:button size="sm" variant="ghost" wire:click="reactivatePackage({{ $package->id }})" wire:confirm="{{ __('Reactivate this package?') }}">{{ __('Reactivate') }}</flux:button>
                            @else
                                <flux:button size="sm" variant="ghost" wire:click="archivePackage({{ $package->id }})" wire:confirm="{{ __('Archive this package? Existing orders remain intact.') }}">{{ __('Archive') }}</flux:button>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-14 text-center dark:border-white/15 dark:bg-[#1e1f2e]">
                    <i class="fa-duotone fa-box-open-full text-4xl text-zinc-300"></i>
                    <h2 class="mt-4 font-semibold text-zinc-900 dark:text-white">{{ __('No packages yet') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Create a package by combining garments, services and inventory products.') }}</p>
                </div>
            @endforelse
        </div>
        @if ($packages->hasPages()) <div>{{ $packages->links() }}</div> @endif
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($measurements as $measurement)
                <article class="flex flex-col rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-xs font-medium text-zinc-500">{{ $measurement->code }}</p>
                            <h2 class="mt-1 truncate font-semibold text-zinc-900 dark:text-white">{{ $measurement->name }}</h2>
                        </div>
                        <flux:badge size="sm" color="{{ $measurement->is_active ? 'lime' : 'zinc' }}">{{ $measurement->is_active ? __('Active') : __('Archived') }}</flux:badge>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-zinc-50 p-3 text-xs dark:bg-white/5">
                        <div><span class="block text-zinc-400">{{ __('Default unit') }}</span><strong>{{ strtoupper($measurement->default_unit) }}</strong></div>
                        <div><span class="block text-zinc-400">{{ __('Availability') }}</span><strong>{{ $measurement->is_global ? __('Global') : trans_choice(':count category|:count categories', $measurement->garmentCategories->count(), ['count' => $measurement->garmentCategories->count()]) }}</strong></div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @if ($measurement->is_global)<flux:badge size="sm" color="sky">{{ __('All garments') }}</flux:badge>@endif
                        @foreach ($measurement->garmentCategories as $category)
                            <flux:badge size="sm" color="zinc">{{ $category->name }}{{ $category->pivot->is_required ? ' · '.__('Required') : '' }}</flux:badge>
                        @endforeach
                        @if (! $measurement->is_global && $measurement->garmentCategories->isEmpty())<span class="text-xs text-amber-600">{{ __('Not assigned to a garment category') }}</span>@endif
                    </div>
                    @if ($measurement->instructions)<p class="mt-3 line-clamp-2 text-sm text-zinc-500">{{ $measurement->instructions }}</p>@endif
                    @if ($canManageMeasurements)
                        <div class="mt-auto flex items-center justify-end gap-2 pt-4">
                            <flux:button size="sm" variant="ghost" :href="route('order-catalog.measurements.edit', $measurement)" wire:navigate icon="pencil-square">{{ __('Edit') }}</flux:button>
                            @if ($measurement->is_active)
                                <flux:button size="sm" variant="ghost" wire:click="archiveMeasurement({{ $measurement->id }})" wire:confirm="{{ __('Archive this measurement definition? Historical references remain available.') }}">{{ __('Archive') }}</flux:button>
                            @else
                                <flux:button size="sm" variant="ghost" wire:click="reactivateMeasurement({{ $measurement->id }})" wire:confirm="{{ __('Reactivate this measurement definition?') }}">{{ __('Reactivate') }}</flux:button>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-14 text-center dark:border-white/15 dark:bg-[#1e1f2e]">
                    <i class="fa-duotone fa-ruler-combined text-4xl text-zinc-300"></i>
                    <h2 class="mt-4 font-semibold text-zinc-900 dark:text-white">{{ __('No measurement definitions found') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Add reusable dimensional measurements and assign them to garment categories.') }}</p>
                </div>
            @endforelse
        </div>
        @if ($measurements->hasPages()) <div>{{ $measurements->links() }}</div> @endif
    @endif
</div>

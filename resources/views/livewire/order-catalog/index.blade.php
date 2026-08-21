<div class="space-y-5">
    <section class="rounded-2xl bg-[#1e1f2e] p-5 text-white shadow-lg sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-white/55">
                    <a href="{{ route('orders.index') }}" wire:navigate class="hover:text-lime-300">{{ __('Orders Management') }}</a>
                    <i class="fa-solid fa-chevron-right text-[9px]"></i>
                    <span>{{ __('Order Catalog') }}</span>
                </div>
                <h1 class="text-2xl font-semibold">{{ __('Order Catalog') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-white/65">{{ __('Maintain reusable garments, services and commercial packages for faster order entry.') }}</p>
            </div>
            @if ($tab === 'items' && $canManageItems)
                <flux:button :href="route('order-catalog.items.create')" wire:navigate variant="primary" icon="plus">{{ __('New Catalog Item') }}</flux:button>
            @elseif ($tab === 'packages' && $canManagePackages)
                <flux:button :href="route('order-catalog.packages.create')" wire:navigate variant="primary" icon="plus">{{ __('New Package') }}</flux:button>
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
    </div>

    <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-[#1e1f2e]">
        <div class="grid gap-3 md:grid-cols-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ $tab === 'items' ? __('Search name or item code') : __('Search name or package code') }}" />
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
            @if ($branches->count() > 1)
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
    @else
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
    @endif
</div>

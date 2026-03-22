<div>
    <flux:main class="p-6">
        <div class="mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
                <flux:breadcrumbs.item :href="route('orders.index')" wire:navigate>{{ __('Orders') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Board') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        {{-- Page Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Order Board') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                    {{ __('Quick overview of order status across stages.') }}
                </flux:text>
            </div>

            @can('orders.create')
                <flux:button variant="primary" icon="plus" :href="route('orders.create')" wire:navigate>
                    {{ __('New Order') }}
                </flux:button>
            @endcan
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                {{ session('error') }}
            </div>
        @endif

        {{-- Search --}}
        <div class="mb-6">
            <flux:input
                wire:model.blur="search"
                placeholder="Search by order no, customer name, or phone..."
                icon="magnifying-glass"
                class="max-w-md"
            />
        </div>

        {{-- Board Columns --}}
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- New Column --}}
            <div class="flex flex-col">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-blue-500"></div>
                        <flux:heading size="lg">New</flux:heading>
                        <flux:badge color="blue" size="sm">{{ $newCount }}</flux:badge>
                    </div>
                </div>

                <div class="flex-1 space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    @forelse ($newOrders as $order)
                        @include('livewire.orders.partials.board-card', ['order' => $order])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <x-icon name="inbox" class="size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:text class="mt-2 text-zinc-500">No new orders</flux:text>
                        </div>
                    @endforelse

                    @if ($newHasMore)
                        <flux:button variant="ghost" class="w-full" wire:click="loadMoreNew">
                            Load More
                        </flux:button>
                    @endif
                </div>
            </div>

            {{-- In Progress Column --}}
            <div class="flex flex-col">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-amber-500"></div>
                        <flux:heading size="lg">In Progress</flux:heading>
                        <flux:badge color="amber" size="sm">{{ $inProgressCount }}</flux:badge>
                    </div>
                </div>

                <div class="flex-1 space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    @forelse ($inProgressOrders as $order)
                        @include('livewire.orders.partials.board-card', ['order' => $order])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <x-icon name="inbox" class="size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:text class="mt-2 text-zinc-500">No orders in progress</flux:text>
                        </div>
                    @endforelse

                    @if ($inProgressHasMore)
                        <flux:button variant="ghost" class="w-full" wire:click="loadMoreInProgress">
                            Load More
                        </flux:button>
                    @endif
                </div>
            </div>

            {{-- Ready Column --}}
            <div class="flex flex-col">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-emerald-500"></div>
                        <flux:heading size="lg">Ready</flux:heading>
                        <flux:badge color="emerald" size="sm">{{ $readyCount }}</flux:badge>
                    </div>
                </div>

                <div class="flex-1 space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    @forelse ($readyOrders as $order)
                        @include('livewire.orders.partials.board-card', ['order' => $order, 'hideCompleteButton' => true])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <x-icon name="inbox" class="size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:text class="mt-2 text-zinc-500">No ready orders</flux:text>
                        </div>
                    @endforelse

                    @if ($readyHasMore)
                        <flux:button variant="ghost" class="w-full" wire:click="loadMoreReady">
                            Load More
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </flux:main>
</div>

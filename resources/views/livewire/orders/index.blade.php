<div>
    <flux:main class="p-0">
        <div class="mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
                <flux:breadcrumbs.item>{{ __('Orders') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        {{-- Page Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Orders Management') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                    {{ __('View and manage tailoring orders.') }}
                </flux:text>
            </div>

            @can('orders.create')
                <flux:button variant="primary" icon="plus" :href="route('orders.create')" wire:navigate>
                    {{ __('New Order') }}
                </flux:button>
            @endcan
        </div>

        {{-- Payments Card (show when user can create payments) --}}
        @if ($canCreatePayments)
            <div class="mb-6">
                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="md">{{ __('Payments') }}</flux:heading>
                            <flux:text class="text-zinc-500">{{ __('Record and view payments related to orders.') }}</flux:text>
                        </div>

                        @if ($canViewPayments)
                            <div class="flex items-center gap-2">
                                <flux:button variant="outline" :href="route('payments.index')" wire:navigate>
                                    {{ __('View Payments') }}
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </flux:card>
            </div>
        @endif

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

        {{-- Filters --}}
        <flux:card class="mb-6">
            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search order, customer..."
                    icon="magnifying-glass"
                />

                <flux:select wire:model.live="statusFilter">
                    <flux:select.option value="">All Status</flux:select.option>
                    @foreach ($statuses as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="priorityFilter">
                    <flux:select.option value="">All Priorities</flux:select.option>
                    @foreach ($priorities as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="tailorFilter">
                    <flux:select.option value="">All Tailors</flux:select.option>
                    @foreach ($tailors as $id => $name)
                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <flux:input
                        wire:model.live="dateFrom"
                        type="date"
                        placeholder="From date"
                    />
                    <flux:input
                        wire:model.live="dateTo"
                        type="date"
                        placeholder="To date"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <flux:select wire:model.live="perPage" class="flex-1">
                        <flux:select.option value="15">15</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                    </flux:select>

                    <flux:button size="sm" variant="ghost" wire:click="clearFilters" title="Clear Filters">
                        <x-icon name="close" class="size-4" />
                    </flux:button>
                </div>
            </div>
        </flux:card>

        {{-- Orders Table --}}
        <flux:card>
            <div class="overflow-x-auto custom-scrollbar-light">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-sm font-semibold text-zinc-900 dark:text-white">
                            <th class="px-4 py-3">{{ __('Order No') }}</th>
                            <th class="px-4 py-3">{{ __('Customer') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                            <th class="px-4 py-3">{{ __('Due Date') }}</th>
                            @if ($canViewFinancials)
                                <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Payment') }}</th>
                            @endif
                            <th class="px-4 py-3">{{ __('Tailor(s)') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($orders as $order)
                            @php
                                $statusColor = $order->status->color();
                                $paymentColor = $order->payment_status->color();
                                $isUrgentOrder = ($order->priority?->value ?? $order->priority) === \App\Enums\Priority::Urgent->value;
                            @endphp
                            <tr class="text-sm text-zinc-700 dark:text-zinc-300" wire:key="order-{{ $order->id }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('orders.show', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                                        {{ $order->order_no }}
                                    </a>
                                    @if ($order->isOverdue())
                                        <flux:badge size="sm" color="red" class="ml-1">Overdue</flux:badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ $order->customer?->name ?? 'N/A' }}</span>
                                        <span class="text-xs text-zinc-500">{{ $order->customer?->phone }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <flux:badge color="{{ $statusColor }}">
                                            {{ $order->status->label() }}
                                        </flux:badge>
                                        @if ($isUrgentOrder)
                                            <span class="inline-flex items-center text-red-500" title="{{ __('Urgent order') }}">
                                                <i class="fa-duotone fa-clock-desk size-4"></i>
                                                <span class="sr-only">{{ __('Urgent') }}</span>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $order->due_date?->format('M d, Y') ?? '-' }}
                                </td>
                                @if ($canViewFinancials)
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ number_format($order->total, 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <flux:badge color="{{ $paymentColor }}">
                                            {{ $order->payment_status->label() }}
                                        </flux:badge>
                                    </td>
                                @endif
                                <td class="px-4 py-3">
                                    @php
                                        $tailorNames = $order->involvedTailorNames();
                                    @endphp

                                    @if ($tailorNames->count() > 1)
                                        <flux:tooltip :content="$tailorNames->implode(', ')" position="top">
                                            <div class="inline-flex cursor-help items-center">
                                                <div class="flex -space-x-2">
                                                    @foreach ($tailorNames->take(3) as $tailorName)
                                                        <span class="inline-flex size-7 items-center justify-center rounded-full border border-white bg-zinc-100 text-zinc-600 shadow-sm dark:border-zinc-800 dark:bg-zinc-700 dark:text-zinc-200">
                                                            <x-icon name="person" class="size-4" />
                                                            <span class="sr-only">{{ $tailorName }}</span>
                                                        </span>
                                                    @endforeach

                                                    @if ($tailorNames->count() > 3)
                                                        <span class="inline-flex size-7 items-center justify-center rounded-full border border-white bg-zinc-900 text-xs font-semibold text-white shadow-sm dark:border-zinc-800 dark:bg-zinc-200 dark:text-zinc-900">
                                                            +{{ $tailorNames->count() - 3 }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </flux:tooltip>
                                    @elseif ($tailorNames->isNotEmpty())
                                        <span>{{ $tailorNames->first() }}</span>
                                    @else
                                        <span>-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end">
                                        <flux:dropdown position="bottom" align="end">
                                            <button type="button" class="flex items-center justify-center size-8 rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                                                <i class="fa-duotone fa-ellipsis-vertical size-4"></i>
                                                <span class="sr-only">{{ __('Actions') }}</span>
                                            </button>

                                            <flux:menu>
                                                <flux:menu.item :href="route('orders.show', $order)" wire:navigate>
                                                    <i class="fa-duotone fa-eye text-sm text-zinc-400 mr-2"></i>
                                                    {{ __('View Order') }}
                                                </flux:menu.item>

                                                @can('orders.update')
                                                    <flux:menu.item :href="route('orders.edit', $order)" wire:navigate>
                                                        <i class="fa-duotone fa-pen-to-square text-sm text-zinc-400 mr-2"></i>
                                                        {{ __('Edit Order') }}
                                                    </flux:menu.item>
                                                @endcan
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canViewFinancials ? 8 : 6 }}" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <x-icon name="description" class="size-12 text-zinc-300 dark:text-zinc-600" />
                                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                                            {{ __('No orders found.') }}
                                        </flux:text>
                                        @can('orders.create')
                                            <flux:button size="sm" variant="primary" :href="route('orders.create')" wire:navigate>
                                                {{ __('Create your first order') }}
                                            </flux:button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="mt-4 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $orders->links() }}
                </div>
            @endif
        </flux:card>
    </flux:main>
</div>

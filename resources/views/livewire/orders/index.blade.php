<div x-data="{ filtersOpen: false }">
    <flux:main class="p-0">
        <section class="mb-6 overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:p-6" style="background: linear-gradient(135deg, var(--tailorpro-primary) 0%, color-mix(in srgb, var(--tailorpro-primary) 88%, #ffffff 12%) 100%);" data-orders-workspace-header>
            <flux:breadcrumbs class="mb-5 text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item class="!text-white">{{ __('Orders') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <flux:heading size="xl" class="!text-white">{{ __('Orders Management') }}</flux:heading>
                    <p class="mt-1 text-sm text-white/70">{{ __('View, track and manage tailoring orders.') }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($canViewPayments)
                        <flux:button variant="outline" icon="banknotes" :href="route('payments.index')" class="!border-white/25 !bg-white/10 !text-white hover:!bg-white/20" wire:navigate>
                            {{ __('View Payments') }}
                        </flux:button>
                    @endif
                    @can('orders.create')
                        <flux:button variant="primary" icon="plus" :href="route('orders.create')" wire:navigate>
                            {{ __('New Order') }}
                        </flux:button>
                    @endcan
                </div>
            </div>
        </section>

        @if ($canViewKpis)
        @php
            $compactValue = static function (float|int $value, bool $money = false): string {
                $absolute = abs((float) $value);
                $formatted = number_format($value, 0);

                foreach ([[1_000_000_000, 'B'], [1_000_000, 'M'], [1_000, 'K']] as [$threshold, $suffix]) {
                    if ($absolute >= $threshold) {
                        $formatted = rtrim(rtrim(number_format(round($value / $threshold, 1), 1, '.', ''), '0'), '.').$suffix;
                        break;
                    }
                }

                return $money ? 'Tsh '.$formatted : $formatted;
            };
            $kpiCards = [
                ['key' => 'orders', 'label' => __('Orders'), 'icon' => 'fa-bag-shopping', 'money' => false, 'color' => 'text-sky-600 bg-sky-50 dark:bg-sky-950/50 dark:text-sky-300'],
                ['key' => 'amount', 'label' => __('Amount'), 'icon' => 'fa-coins', 'money' => true, 'color' => 'text-violet-600 bg-violet-50 dark:bg-violet-950/50 dark:text-violet-300'],
                ['key' => 'paid', 'label' => __('Paid'), 'icon' => 'fa-circle-check', 'money' => true, 'color' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/50 dark:text-emerald-300'],
                ['key' => 'balance', 'label' => __('Balance'), 'icon' => 'fa-scale-balanced', 'money' => true, 'color' => 'text-amber-600 bg-amber-50 dark:bg-amber-950/50 dark:text-amber-300'],
            ];
        @endphp

        <section class="mb-4" aria-labelledby="orders-kpis-heading">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 id="orders-kpis-heading" class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Order overview') }}</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $kpiPeriodLabel }} · {{ __('compared with the previous month') }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($kpiCards as $card)
                    @continue($card['money'] && ! $canViewFinancials)
                    @php
                        $metric = $kpis[$card['key']];
                        $growth = (float) $metric['growth'];
                        $fullValue = $card['money'] ? money_tzs($metric['value']) : number_format($metric['value']);
                    @endphp
                    <flux:card class="relative overflow-hidden">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                                <flux:tooltip :content="$fullValue" position="top">
                                    <p class="mt-2 cursor-help text-2xl font-bold tracking-tight text-zinc-950 dark:text-white" aria-label="{{ $card['label'] }}: {{ $fullValue }}">
                                        {{ $compactValue($metric['value'], $card['money']) }}
                                    </p>
                                </flux:tooltip>
                            </div>
                            <span class="inline-flex size-10 items-center justify-center rounded-xl {{ $card['color'] }}">
                                <i class="fa-duotone {{ $card['icon'] }}" aria-hidden="true"></i>
                            </span>
                        </div>
                        <div class="mt-4 flex items-center gap-1.5 text-xs">
                            <span class="inline-flex items-center gap-1 font-semibold {{ $growth > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($growth < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-500') }}">
                                <i class="fa-solid {{ $growth > 0 ? 'fa-arrow-trend-up' : ($growth < 0 ? 'fa-arrow-trend-down' : 'fa-minus') }}" aria-hidden="true"></i>
                                {{ number_format(abs($growth), 1) }}%
                            </span>
                            <span class="text-zinc-500 dark:text-zinc-400">{{ __('vs previous month') }}</span>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </section>
        @endif

        <div class="mb-4 flex justify-end">
            <flux:button variant="ghost" icon="funnel" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="orders-filters">
                {{ __('Filters') }}
            </flux:button>
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

        {{-- Filters --}}
        <flux:card id="orders-filters" x-show="filtersOpen" x-collapse x-cloak class="mb-6" wire:key="orders-filter-panel">
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

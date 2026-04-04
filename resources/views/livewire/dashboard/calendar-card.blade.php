@php
    $calendarBaseUrl = \Illuminate\Support\Facades\Route::has('calendar.index')
        ? route('calendar.index')
        : url('/calendar');
    $monthData = $this->monthData;
    $upcomingDueOrders = $this->upcomingDueOrders;
@endphp

<div class="grid gap-4 md:grid-cols-2 md:items-start">
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-4 lg:p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 flex flex-col">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Calendar') }}</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('4-week preview') }}</p>
            </div>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    wire:click="previousMonth"
                    class="flex size-8 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    aria-label="{{ __('Previous month') }}"
                >
                    <i class="fa-duotone fa-chevron-left text-xs"></i>
                </button>

                <button
                    type="button"
                    wire:click="nextMonth"
                    class="flex size-8 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    aria-label="{{ __('Next month') }}"
                >
                    <i class="fa-duotone fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $monthData['month_label'] }}</p>
            <a href="{{ $calendarBaseUrl }}" wire:navigate class="text-xs font-medium text-lime-600 hover:text-lime-700 dark:text-lime-400 dark:hover:text-lime-300">
                {{ __('Open full calendar') }}
            </a>
        </div>

        <div class="mt-3 flex-1">
            <div class="grid gap-1 text-center" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                @foreach ($monthData['weekdays'] as $weekday)
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ $weekday }}</span>
                @endforeach
            </div>

            <div class="mt-2 space-y-2">
                @foreach ($monthData['weeks'] as $week)
                    <div class="grid gap-1.5" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                        @foreach ($week as $day)
                            @php
                                $stateStyle = '';

                                if ($day['has_orders'] && $day['has_bookings']) {
                                    $stateClasses = 'text-zinc-900 dark:text-zinc-100';
                                    $stateStyle = 'background: linear-gradient(135deg, oklch(76.9% 0.188 70.08) 0%, #ddd6fe 100%);';
                                } elseif ($day['has_orders']) {
                                    $stateClasses = 'text-amber-900 dark:text-amber-100';
                                    $stateStyle = 'background-color: oklch(76.9% 0.188 70.08);';
                                } elseif ($day['has_bookings']) {
                                    $stateClasses = 'bg-violet-200 text-violet-900 dark:bg-violet-700/55 dark:text-violet-100';
                                } else {
                                    $stateClasses = 'bg-transparent text-zinc-700 dark:text-zinc-200 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-800/70';
                                }

                                if (! $day['in_month']) {
                                    $stateClasses .= ' text-zinc-400 dark:text-zinc-500';
                                }

                                if ($day['is_today']) {
                                    $stateClasses .= ' ring-2 ring-lime-500 dark:ring-lime-400';
                                }
                            @endphp

                            <a
                                href="{{ $calendarBaseUrl . '?date=' . urlencode($day['date']) }}"
                                wire:navigate
                                class="group flex h-10 items-center justify-center"
                                title="{{ $day['date'] }}"
                            >
                                <span
                                    class="relative flex size-8 items-center justify-center rounded-full text-xs font-semibold transition {{ $stateClasses }}"
                                    @if ($stateStyle !== '') style="{{ $stateStyle }}" @endif
                                >
                                    {{ $day['day'] }}

                                    @if ($day['has_bookings'])
                                        <span class="absolute -bottom-0.5 -left-0.5 size-1.5 rounded-full bg-violet-500"></span>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2 text-[11px]">
            <span class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-300">
                <span class="size-2.5 rounded-full bg-lime-500"></span>
                {{ __('Today') }}
            </span>
            <span class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-300">
                <span class="size-2.5 rounded-full" style="background-color: oklch(76.9% 0.188 70.08);"></span>
                {{ __('Order due date') }}
            </span>
            <span class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-300">
                <span class="size-2.5 rounded-full bg-violet-500"></span>
                {{ __('Booking date') }}
            </span>
        </div>

        @if (! $monthData['can_view_order_dates'])
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('You do not have permission to view order due dates.') }}</p>
        @endif

        @if ($monthData['requires_branch_selection'])
            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">{{ __('Select a branch to view due-date highlights.') }}</p>
        @endif
    </div>

    <div class="flex flex-col md:self-start">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold leading-tight text-zinc-900 dark:text-white">{{ __('Upcoming Due Orders') }}</h2>
            </div>

            @if ($upcomingDueOrders['can_view_order_dates'])
                <a href="{{ route('orders.index') }}" wire:navigate class="text-xs font-semibold text-lime-600 hover:text-lime-700 dark:text-lime-400 dark:hover:text-lime-300">
                    {{ __('View all') }}
                </a>
            @endif
        </div>

        @if ($upcomingDueOrders['requires_branch_selection'])
            <div class="mt-4 rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/20 p-3 text-sm text-amber-700 dark:text-amber-300">
                {{ __('Select a branch to view upcoming due orders.') }}
            </div>
        @elseif (! $upcomingDueOrders['can_view_order_dates'])
            <div class="mt-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/30 p-3 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Your role cannot view order due dates.') }}
            </div>
        @elseif (count($upcomingDueOrders['items']) === 0)
            <div class="mt-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/30 p-4 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('No upcoming due orders in the next 3 months.') }}
            </div>
        @else
            <div class="mt-4 space-y-2">
                @foreach ($upcomingDueOrders['items'] as $order)
                    @php
                        $daysToDue = (int) ($order['days_to_due'] ?? 0);
                        $isOverdue = $daysToDue < 0;
                        $priorityHoverColors = [
                            'low' => '#f59e0b',
                            'normal' => '#10b981',
                            'high' => '#6366f1',
                            'urgent' => '#ef4444',
                        ];
                        $priorityHoverColor = $priorityHoverColors[$order['priority'] ?? 'normal'] ?? $priorityHoverColors['normal'];
                    @endphp
                    <a
                        href="{{ $order['url'] }}"
                        wire:navigate
                        class="group relative overflow-hidden flex items-center justify-between gap-3 rounded-2xl px-4 py-3 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900/60 transition-all duration-200 hover:shadow-[0_14px_26px_-16px_rgba(0,0,0,0.5)]"
                        style="--priority-hover-color: {{ $priorityHoverColor }};"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="truncate text-[13px] font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
                                {{ $order['customer_name'] }}
                            </p>
                            <p class="truncate text-[11px] font-medium text-zinc-600 dark:text-zinc-300">
                                {{ $order['order_no'] ?: $order['title'] }}
                            </p>
                        </div>

                        <div class="text-right shrink-0">
                            <p class="text-[12px] leading-tight font-bold {{ $isOverdue ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                {{ $daysToDue }}{{ __('days') }}
                            </p>
                        </div>

                        <div class="pointer-events-none absolute inset-0 rounded-2xl opacity-0 transition-opacity duration-200 group-hover:opacity-100" style="box-shadow: inset 0 0 0 1px var(--priority-hover-color), inset 0 0 22px -16px var(--priority-hover-color);"></div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

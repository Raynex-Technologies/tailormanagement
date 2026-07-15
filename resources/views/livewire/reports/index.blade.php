<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
        <flux:breadcrumbs.item>{{ __('Reports') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @php
        $summary = $this->summary;
        $isProfit = $summary['result_type'] === 'profit';
        $isLoss = $summary['result_type'] === 'loss';
    @endphp

    {{-- Page Header --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="flex items-start gap-4">
            <div class="flex items-center justify-center size-12 rounded-xl bg-violet-100 dark:bg-violet-900/30">
                <i class="fa-duotone fa-chart-mixed size-6 text-violet-600 dark:text-violet-400"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Reports Dashboard') }}</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Overall business performance with profit/loss tracking.') }}</p>
            </div>
        </div>
    </div>

    {{-- Period Filters --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:select wire:model.live="period" label="{{ __('Period') }}">
                @foreach($this->periodOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="selectedMonth" label="{{ __('Month') }}" :disabled="$period !== 'month'">
                @foreach($this->monthOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>

            <div class="flex items-end">
                <div class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Showing') }}</p>
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $this->periodLabel }}</p>
                </div>
            </div>
        </div>

        <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('Profit/Loss includes both regular expenses and order expenses.') }}
        </p>
    </div>

    {{-- KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 mb-3">
                <i class="fa-duotone fa-wallet size-5 text-emerald-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ money_tzs($summary['income_total']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Revenue') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-rose-50 dark:bg-rose-900/30 mb-3">
                <i class="fa-duotone fa-receipt size-5 text-rose-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-rose-600 dark:text-rose-400">{{ money_tzs($summary['total_expenses']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Expenses') }}</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Order expenses:') }} {{ money_tzs($summary['order_expenses_total']) }}
            </p>
        </div>

        <div @class([
            'rounded-2xl p-5 shadow-sm border',
            'border-emerald-200/70 bg-emerald-50/60 dark:border-emerald-800/60 dark:bg-emerald-900/20' => $isProfit,
            'border-rose-200/70 bg-rose-50/60 dark:border-rose-800/60 dark:bg-rose-900/20' => $isLoss,
            'border-zinc-200/50 bg-white dark:border-zinc-700/50 dark:bg-zinc-800/50' => ! $isProfit && ! $isLoss,
        ])>
            <div class="flex items-center justify-center size-11 rounded-xl mb-3 bg-white/70 dark:bg-zinc-900/50">
                <i class="fa-duotone fa-scale-balanced size-5 {{ $isProfit ? 'text-emerald-500' : ($isLoss ? 'text-rose-500' : 'text-zinc-500') }}"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight {{ $isProfit ? 'text-emerald-600 dark:text-emerald-400' : ($isLoss ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-white') }}">
                {{ money_tzs($summary['net_result']) }}
            </p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $isProfit ? __('Net Profit') : ($isLoss ? __('Net Loss') : __('Break-even')) }}
            </p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 mb-3">
                <i class="fa-duotone fa-percent size-5 text-indigo-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                {{ $summary['profit_margin'] === null ? 'N/A' : number_format($summary['profit_margin'], 2) . '%' }}
            </p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Profit Margin') }}</p>
        </div>
    </div>

    {{-- Performance Breakdown --}}
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
            <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Profit & Loss Breakdown') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Based on selected period') }}: {{ $this->periodLabel }}</p>
            </div>

            <dl class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                <div class="flex items-center justify-between px-5 py-4">
                    <dt class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Revenue (Payments + POS Sales)') }}</dt>
                    <dd class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ money_tzs($summary['income_total']) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-4">
                    <dt class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Regular Expenses') }}</dt>
                    <dd class="text-sm font-semibold text-rose-600 dark:text-rose-400">{{ money_tzs($summary['regular_expenses_total']) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-4">
                    <dt class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Order Expenses') }}</dt>
                    <dd class="text-sm font-semibold text-rose-600 dark:text-rose-400">{{ money_tzs($summary['order_expenses_total']) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-4">
                    <dt class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Total Expenses') }}</dt>
                    <dd class="text-sm font-semibold text-rose-600 dark:text-rose-400">{{ money_tzs($summary['total_expenses']) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-4">
                    <dt class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Net Result') }}</dt>
                    <dd class="text-base font-bold {{ $isProfit ? 'text-emerald-600 dark:text-emerald-400' : ($isLoss ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-white') }}">
                        {{ money_tzs($summary['net_result']) }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Orders in Period') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($summary['orders_count']) }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Booked Value') }}: {{ money_tzs($summary['orders_value']) }}</p>
            </div>

            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Revenue Entries') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($summary['payments_count']) }}</p>
            </div>

            <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Expense Entries') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($summary['expenses_count']) }}</p>
            </div>
        </div>
    </div>

    {{-- Detailed Report Links --}}
    <div class="space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Detailed Reports') }}</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Drill down into specific modules.') }}</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($this->reportLinks as $report)
                <a
                    href="{{ route($report['route']) }}"
                    wire:navigate
                    class="group relative overflow-hidden rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 transition-all hover:shadow-md hover:border-{{ $report['color'] }}-300 dark:hover:border-{{ $report['color'] }}-700/50"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center justify-center size-12 rounded-xl bg-{{ $report['color'] }}-100 dark:bg-{{ $report['color'] }}-900/30">
                            <x-icon :name="$report['icon']" class="size-6 text-{{ $report['color'] }}-600 dark:text-{{ $report['color'] }}-400" />
                        </div>
                        <i class="fa-duotone fa-arrow-right size-4 text-zinc-300 dark:text-zinc-600 transition-transform group-hover:translate-x-1 group-hover:text-{{ $report['color'] }}-500"></i>
                    </div>

                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white group-hover:text-{{ $report['color'] }}-600 dark:group-hover:text-{{ $report['color'] }}-400 transition-colors">
                        {{ $report['name'] }}
                    </h3>
                    <p class="mt-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $report['description'] }}
                    </p>
                </a>
            @endforeach
        </div>
    </div>
</flux:main>

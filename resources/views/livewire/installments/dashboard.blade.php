<flux:main class="space-y-6 p-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Installments') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="rounded-2xl border border-zinc-200/50 bg-white p-6 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex size-12 items-center justify-center rounded-xl bg-cyan-100 dark:bg-cyan-900/30">
                    <i class="fa-duotone fa-money-check-dollar-pen size-6 text-cyan-600 dark:text-cyan-400"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Installments Dashboard') }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Monitor package-based credit sales, due payments, and collection performance.') }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @can('installments.manage')
                    <flux:button :href="route('installments.plans.create')" wire:navigate variant="primary">
                        <i class="fa-duotone fa-plus mr-1.5 size-4"></i>
                        {{ __('New Plan') }}
                    </flux:button>
                @endcan
                @can('installments.packages.manage')
                    <flux:button :href="route('installments.packages.index')" wire:navigate variant="ghost">
                        {{ __('Manage Packages') }}
                    </flux:button>
                @endcan
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Financed') }}</p>
            <p class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ money_tzs($stats['total_financed']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Collected So Far') }}</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ money_tzs($stats['total_collected']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Outstanding Balance') }}</p>
            <p class="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ money_tzs($stats['outstanding_balance']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active Plans') }}</p>
            <p class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($stats['active_plans']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Completed Plans') }}</p>
            <p class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($stats['completed_plans']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Overdue Plans') }}</p>
            <p class="mt-2 text-3xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($stats['overdue_plans']) }}</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-zinc-200/50 bg-white shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-700/50">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Upcoming Payments') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('Due Date') }}</th>
                                <th class="px-4 py-3">{{ __('Customer') }}</th>
                                <th class="px-4 py-3">{{ __('Plan') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Amount Due') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($upcomingSchedules as $schedule)
                                <tr class="text-sm text-zinc-900 dark:text-zinc-100">
                                    <td class="px-4 py-3">{{ $schedule->due_date?->format('M d, Y') }}</td>
                                    <td class="px-4 py-3">{{ $schedule->installmentPlan?->customer?->name }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('installments.plans.show', $schedule->installmentPlan) }}" wire:navigate class="font-medium text-cyan-600 dark:text-cyan-400">
                                            {{ $schedule->installmentPlan?->plan_no }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ money_tzs($schedule->outstanding_amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('No upcoming payments in the next 14 days.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-200/50 bg-white shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-700/50">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Overdue Payments') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('Due Date') }}</th>
                                <th class="px-4 py-3">{{ __('Customer') }}</th>
                                <th class="px-4 py-3">{{ __('Plan') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Outstanding') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($overdueSchedules as $schedule)
                                <tr class="text-sm text-zinc-900 dark:text-zinc-100">
                                    <td class="px-4 py-3 text-rose-600 dark:text-rose-400">{{ $schedule->due_date?->format('M d, Y') }}</td>
                                    <td class="px-4 py-3">{{ $schedule->installmentPlan?->customer?->name }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('installments.plans.show', $schedule->installmentPlan) }}" wire:navigate class="font-medium text-cyan-600 dark:text-cyan-400">
                                            {{ $schedule->installmentPlan?->plan_no }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ money_tzs($schedule->outstanding_amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('No overdue payments.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Payment Frequency Mix') }}</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($frequencyBreakdown as $label => $count)
                        <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-900/40">
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $label }}</span>
                            <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($count) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No frequency data yet.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Top Packages') }}</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($topPackages as $package)
                        <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-900/40">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-zinc-900 dark:text-white">{{ $package->name }}</p>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ money_tzs($package->price) }} · {{ $package->durationLabel() }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-semibold text-zinc-900 dark:text-white">{{ number_format($package->installment_plans_count) }}</p>
                                    <p class="text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Plans') }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No packages linked to plans yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</flux:main>

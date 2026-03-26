<flux:main class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
        <flux:breadcrumbs.item :href="route('installments.dashboard')" wire:navigate>{{ __('Installments') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Plans') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Installment Plans') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Track customer package assignments, repayment progress, and overdue balances.') }}</flux:text>
        </div>

        @can('installments.manage')
            <flux:button :href="route('installments.plans.create')" wire:navigate variant="primary">
                {{ __('New Plan') }}
            </flux:button>
        @endcan
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active') }}</p>
            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($stats['active']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</p>
            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($stats['completed']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Financed') }}</p>
            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ money_tzs($stats['financed']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Outstanding') }}</p>
            <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ money_tzs($stats['outstanding']) }}</p>
        </div>
    </div>

    <flux:card>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search plan, package, customer...') }}" />
            <flux:select wire:model.live="statusFilter">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="frequencyFilter">
                <option value="">{{ __('All Frequencies') }}</option>
                @foreach($frequencies as $frequency)
                    <option value="{{ $frequency->value }}">{{ $frequency->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="perPage">
                <option value="15">15 {{ __('per page') }}</option>
                <option value="25">25 {{ __('per page') }}</option>
                <option value="50">50 {{ __('per page') }}</option>
            </flux:select>
        </div>
    </flux:card>

    <div class="overflow-hidden rounded-2xl border border-zinc-200/50 bg-white shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">{{ __('Plan') }}</th>
                        <th class="px-4 py-3">{{ __('Customer') }}</th>
                        <th class="px-4 py-3">{{ __('Package') }}</th>
                        <th class="px-4 py-3">{{ __('Frequency') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Paid') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Remaining') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($plans as $plan)
                        @php($paid = (float) ($plan->payments_sum_amount ?? 0))
                        <tr class="text-sm text-zinc-900 dark:text-zinc-100">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $plan->plan_no }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $plan->start_date?->format('M d, Y') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ $plan->customer?->name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $plan->customer?->phone }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ $plan->package_name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($plan->installments_count) }} {{ __('installments') }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $plan->payment_frequency?->label() }}</td>
                            <td class="px-4 py-3 text-right">{{ money_tzs($paid) }}</td>
                            <td class="px-4 py-3 text-right">{{ money_tzs(max(0, (float) $plan->package_price - $paid)) }}</td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" color="{{ $plan->status?->color() ?? 'zinc' }}">
                                    {{ $plan->status?->label() ?? $plan->status }}
                                </flux:badge>
                                @if($plan->overdue_schedules_count > 0)
                                    <flux:badge size="sm" color="red" class="ml-1">{{ __('Overdue') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button :href="route('installments.plans.show', $plan)" wire:navigate size="sm" variant="ghost">
                                    {{ __('Open') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('No installment plans found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($plans->hasPages())
            <div class="border-t border-zinc-100 px-5 py-3 dark:border-zinc-700/50">
                {{ $plans->links() }}
            </div>
        @endif
    </div>
</flux:main>

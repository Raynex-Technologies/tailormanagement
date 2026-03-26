<flux:main class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
        <flux:breadcrumbs.item :href="route('installments.dashboard')" wire:navigate>{{ __('Installments') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Analytics') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="rounded-2xl border border-zinc-200/50 bg-white p-6 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Installment Analytics') }}</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Portfolio-level performance for package-based credit sales.') }}</p>
            </div>

            @can('reports.export')
                <flux:button wire:click="export" variant="primary" size="sm">
                    <i class="fa-duotone fa-download mr-1.5 size-4"></i>
                    {{ __('Export CSV') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input wire:model.live="dateFrom" type="date" label="{{ __('From Date') }}" />
            <flux:input wire:model.live="dateTo" type="date" label="{{ __('To Date') }}" />
            <flux:select wire:model.live="status" label="{{ __('Status') }}">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="frequency" label="{{ __('Frequency') }}">
                <option value="">{{ __('All Frequencies') }}</option>
                @foreach($frequencies as $frequencyOption)
                    <option value="{{ $frequencyOption->value }}">{{ $frequencyOption->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="search" label="{{ __('Search') }}" placeholder="{{ __('Customer, plan, package...') }}" icon="magnifying-glass" />
        </div>

        <div class="mt-4 flex justify-end">
            <flux:button wire:click="resetFilters" variant="ghost" size="sm">
                {{ __('Reset Filters') }}
            </flux:button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Financed') }}</p>
            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ money_tzs($this->summary['total_financed']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Collected') }}</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ money_tzs($this->summary['total_collected']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Outstanding') }}</p>
            <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ money_tzs($this->summary['outstanding_balance']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active Plans') }}</p>
            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->summary['active_count']) }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Overdue Plans') }}</p>
            <p class="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($this->summary['overdue_count']) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200/50 bg-white shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">{{ __('Start Date') }}</th>
                        <th class="px-4 py-3">{{ __('Plan') }}</th>
                        <th class="px-4 py-3">{{ __('Customer') }}</th>
                        <th class="px-4 py-3">{{ __('Package') }}</th>
                        <th class="px-4 py-3">{{ __('Frequency') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Financed') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Collected') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Remaining') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->rows as $row)
                        @php($collected = (float) ($row->payments_sum_amount ?? 0))
                        <tr class="text-sm text-zinc-900 dark:text-zinc-100">
                            <td class="px-4 py-3">{{ $row->start_date?->format('M d, Y') }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('installments.plans.show', $row) }}" wire:navigate class="font-medium text-cyan-600 dark:text-cyan-400">
                                    {{ $row->plan_no }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $row->customer?->name }}</td>
                            <td class="px-4 py-3">{{ $row->package_name }}</td>
                            <td class="px-4 py-3">{{ $row->payment_frequency?->label() }}</td>
                            <td class="px-4 py-3 text-right">{{ money_tzs($row->package_price) }}</td>
                            <td class="px-4 py-3 text-right">{{ money_tzs($collected) }}</td>
                            <td class="px-4 py-3 text-right">{{ money_tzs(max(0, (float) $row->package_price - $collected)) }}</td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" color="{{ $row->status?->color() ?? 'zinc' }}">
                                    {{ $row->status?->label() ?? $row->status }}
                                </flux:badge>
                                @if($row->overdue_schedules_count > 0)
                                    <flux:badge size="sm" color="red" class="ml-1">{{ __('Overdue') }}</flux:badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-16 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('No installment plans found for the selected filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->rows->hasPages())
            <div class="border-t border-zinc-100 px-5 py-3 dark:border-zinc-700/50">
                {{ $this->rows->links() }}
            </div>
        @endif
    </div>
</flux:main>

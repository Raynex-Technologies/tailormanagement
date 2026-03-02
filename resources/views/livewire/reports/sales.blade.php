<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
        <flux:breadcrumbs.item href="{{ route('reports.index') }}" wire:navigate>{{ __('Reports') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Sales Report') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Header --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex items-center justify-center size-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                    <i class="fa-duotone fa-money-bills size-6 text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Sales Report') }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Payment transactions and revenue analysis.') }}</p>
                </div>
            </div>
            @can('reports.export')
                <flux:button wire:click="export" variant="primary" size="sm">
                    <i class="fa-duotone fa-download mr-1.5 size-4"></i>
                    {{ __('Export CSV') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input wire:model.live="dateFrom" type="date" label="{{ __('From Date') }}" />
            <flux:input wire:model.live="dateTo" type="date" label="{{ __('To Date') }}" />
            <flux:select wire:model.live="method" label="{{ __('Payment Method') }}">
                <option value="">{{ __('All Methods') }}</option>
                @foreach($this->paymentMethods as $m)
                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search order/customer...') }}" label="{{ __('Search') }}" icon="magnifying-glass" />
            <div class="flex items-end">
                <flux:button wire:click="resetFilters" variant="ghost" size="sm">
                    <i class="fa-duotone fa-xmark mr-1 size-4"></i>
                    {{ __('Reset') }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 mb-3">
                <i class="fa-duotone fa-coins size-5 text-emerald-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ money_tzs($this->summary['total_received']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Received') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 mb-3">
                <i class="fa-duotone fa-hashtag size-5 text-indigo-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($this->summary['total_count']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Number of Payments') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-blue-50 dark:bg-blue-900/30 mb-3">
                <i class="fa-duotone fa-scale-balanced size-5 text-blue-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ money_tzs($this->summary['avg_payment']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Average Payment') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-amber-50 dark:bg-amber-900/30 mb-3">
                <i class="fa-duotone fa-trophy size-5 text-amber-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->summary['top_method'] }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Top Method') }} &middot; {{ money_tzs($this->summary['top_method_amount']) }}</p>
        </div>
    </div>

    {{-- Data Table --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
        <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                    <i class="fa-duotone fa-table-list size-5 text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Payment Transactions') }}</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Individual payment records for the selected period') }}</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">{{ __('Order Date') }}</th>
                        <th class="px-4 py-3">{{ __('Order No') }}</th>
                        <th class="px-4 py-3">{{ __('Customer') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                        <th class="px-4 py-3">{{ __('Method') }}</th>
                        <th class="px-4 py-3">{{ __('Reference') }}</th>
                        <th class="px-4 py-3">{{ __('Received By') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->rows as $row)
                        <tr class="text-sm text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{
                                    $row->order_date
                                        ? \Carbon\Carbon::parse($row->order_date)->format('M d, Y')
                                        : \Carbon\Carbon::parse($row->order_created_at)->format('M d, Y')
                                }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('orders.show', $row->order_id) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    {{ $row->order_no }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $row->customer_name }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ money_tzs($row->amount) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                    {{ $row->payment_method_name ?? 'Default' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-zinc-500">{{ $row->reference ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $row->received_by_name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
                                    <i class="fa-duotone fa-money-bills size-7 text-zinc-400 dark:text-zinc-500"></i>
                                </div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No payments found') }}</p>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Try adjusting the filters or date range.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->rows->hasPages())
            <div class="px-5 py-3 border-t border-zinc-100 dark:border-zinc-700/50">
                {{ $this->rows->links() }}
            </div>
        @endif
    </div>
</flux:main>

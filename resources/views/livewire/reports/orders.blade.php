<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
        <flux:breadcrumbs.item href="{{ route('reports.index') }}" wire:navigate>{{ __('Reports') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Orders Report') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Header --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex items-center justify-center size-12 rounded-xl bg-blue-100 dark:bg-blue-900/30">
                    <i class="fa-duotone fa-file-lines size-6 text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Orders Report') }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Order status, turnaround time, and value analysis.') }}</p>
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
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:input wire:model.live="dateFrom" type="date" label="{{ __('From Date') }}" />
            <flux:input wire:model.live="dateTo" type="date" label="{{ __('To Date') }}" />
            <flux:select wire:model.live="status" label="{{ __('Status') }}">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach($this->statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="paymentStatus" label="{{ __('Payment Status') }}">
                <option value="">{{ __('All Payment Statuses') }}</option>
                @foreach($this->paymentStatuses as $ps)
                    <option value="{{ $ps->value }}">{{ $ps->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="tailorId" label="{{ __('Tailor') }}">
                <option value="">{{ __('All Tailors') }}</option>
                @foreach($this->tailors as $tailor)
                    <option value="{{ $tailor->id }}">{{ $tailor->name }}</option>
                @endforeach
            </flux:select>
            <div class="flex items-end gap-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search...') }}" icon="magnifying-glass" class="flex-1" />
                <flux:button wire:click="resetFilters" variant="ghost" size="sm">
                    <i class="fa-duotone fa-xmark size-4"></i>
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Total Orders --}}
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 mb-3">
                <i class="fa-duotone fa-bag-shopping size-5 text-indigo-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($this->summary['total_orders']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Orders') }}</p>
        </div>

        {{-- Completed --}}
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 mb-3">
                <i class="fa-duotone fa-circle-check size-5 text-emerald-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ number_format($this->summary['completed_count']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</p>
        </div>

        {{-- Total Value --}}
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-blue-50 dark:bg-blue-900/30 mb-3">
                <i class="fa-duotone fa-coins size-5 text-blue-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-blue-600 dark:text-blue-400">{{ money_tzs($this->summary['total_value']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Value') }}</p>
        </div>

        {{-- Avg Turnaround --}}
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-amber-50 dark:bg-amber-900/30 mb-3">
                <i class="fa-duotone fa-clock size-5 text-amber-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->summary['avg_turnaround_days'] }} <span class="text-sm font-normal text-zinc-400">{{ __('days') }}</span></p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Avg Turnaround') }}</p>
        </div>
    </div>

    {{-- Data Table --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
        <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-10 rounded-xl bg-blue-100 dark:bg-blue-900/30">
                    <i class="fa-duotone fa-table-list size-5 text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Order Details') }}</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Detailed order data for the selected period') }}</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">{{ __('Order No') }}</th>
                        <th class="px-4 py-3">{{ __('Customer') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3">{{ __('Order Date') }}</th>
                        <th class="px-4 py-3">{{ __('Due Date') }}</th>
                        <th class="px-4 py-3">{{ __('Tailor(s)') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Paid') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->rows as $row)
                        @php
                            $paidAmount = $row->payments->sum('amount');
                            $balance = max(0, $row->total - $paidAmount);
                        @endphp
                        <tr class="text-sm text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('orders.show', $row->id) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    {{ $row->order_no }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $row->customer_name }}</td>
                            <td class="px-4 py-3">
                                <flux:badge color="{{ $row->status->color() }}" size="sm">
                                    {{ $row->status->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row->order_date?->format('M d, Y') ?? $row->created_at->format('M d, Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($row->due_date)
                                    <span class="{{ $row->due_date->isPast() && !in_array($row->status, [\App\Enums\OrderStatus::Completed, \App\Enums\OrderStatus::Delivered, \App\Enums\OrderStatus::Cancelled]) ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">
                                        {{ $row->due_date->format('M d, Y') }}
                                    </span>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $tailorNames = collect();
                                    if ($row->assignedTailor?->name) {
                                        $tailorNames->push($row->assignedTailor->name);
                                    }
                                    $lineTailorNames = $row->lines->pluck('assignedTailor.name')->filter()->unique()->values();
                                    foreach ($lineTailorNames as $lineTailorName) {
                                        if (! $tailorNames->contains($lineTailorName)) {
                                            $tailorNames->push($lineTailorName);
                                        }
                                    }
                                @endphp
                                {{ $tailorNames->isNotEmpty() ? $tailorNames->implode(', ') : __('Unassigned') }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium">{{ money_tzs($row->total) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ money_tzs($paidAmount) }}</td>
                            <td class="px-4 py-3 text-right {{ $balance > 0 ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">
                                {{ money_tzs($balance) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-16 text-center">
                                <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
                                    <i class="fa-duotone fa-file-lines size-7 text-zinc-400 dark:text-zinc-500"></i>
                                </div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No orders found') }}</p>
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

<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
        <flux:breadcrumbs.item href="{{ route('reports.index') }}" wire:navigate>{{ __('Reports') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Capital Audit Report') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Header --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex items-center justify-center size-12 rounded-xl bg-purple-100 dark:bg-purple-900/30">
                    <i class="fa-duotone fa-building-columns size-6 text-purple-600 dark:text-purple-400"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Capital Audit Report') }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Capital allocations, spending, and transactions.') }}</p>
                </div>
            </div>
            @can('reports.export')
                <div class="flex gap-2">
                    <flux:button wire:click="exportAllocations" variant="ghost" size="sm">
                        <i class="fa-duotone fa-download mr-1.5 size-4"></i>
                        {{ __('Export Allocations') }}
                    </flux:button>
                    <flux:button wire:click="exportTransactions" variant="primary" size="sm">
                        <i class="fa-duotone fa-download mr-1.5 size-4"></i>
                        {{ __('Export Transactions') }}
                    </flux:button>
                </div>
            @endcan
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input wire:model.blur="dateFrom" type="date" label="{{ __('Transaction From') }}" />
            <flux:input wire:model.blur="dateTo" type="date" label="{{ __('Transaction To') }}" />
            <flux:select wire:model.blur="accountantId" label="{{ __('Accountant') }}">
                <option value="">{{ __('All Accountants') }}</option>
                @foreach($this->accountants as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.blur="status" label="{{ __('Status') }}">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach($this->statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </flux:select>
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
            <div class="flex items-center justify-center size-11 rounded-xl bg-blue-50 dark:bg-blue-900/30 mb-3">
                <i class="fa-duotone fa-coins size-5 text-blue-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-blue-600 dark:text-blue-400">{{ money_tzs($this->summary['total_allocated']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Allocated') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-red-50 dark:bg-red-900/30 mb-3">
                <i class="fa-duotone fa-receipt size-5 text-red-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-red-600 dark:text-red-400">{{ money_tzs($this->summary['total_spent']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Spent') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 mb-3">
                <i class="fa-duotone fa-wallet size-5 text-emerald-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ money_tzs($this->summary['total_remaining']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Remaining') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 mb-3">
                <i class="fa-duotone fa-folder-open size-5 text-indigo-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($this->summary['open_allocations_count']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Open Allocations') }}</p>
        </div>
    </div>

    {{-- Period Stats --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-violet-50 dark:bg-violet-900/30 mb-3">
                <i class="fa-duotone fa-arrow-right-arrow-left size-5 text-violet-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($this->summary['transaction_count']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Transactions in Period') }} ({{ \Carbon\Carbon::parse($dateFrom)->format('M d') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }})</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-amber-50 dark:bg-amber-900/30 mb-3">
                <i class="fa-duotone fa-arrow-trend-down size-5 text-amber-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-red-600 dark:text-red-400">{{ money_tzs($this->summary['period_debits']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Debits in Period') }}</p>
        </div>
    </div>

    {{-- View Tabs --}}
    <div class="flex gap-2">
        <flux:button wire:click="setView('allocations')" variant="{{ $view === 'allocations' ? 'primary' : 'ghost' }}" size="sm">
            <i class="fa-duotone fa-layer-group mr-1.5 size-4"></i>
            {{ __('Allocations') }}
        </flux:button>
        <flux:button wire:click="setView('transactions')" variant="{{ $view === 'transactions' ? 'primary' : 'ghost' }}" size="sm">
            <i class="fa-duotone fa-list-timeline mr-1.5 size-4"></i>
            {{ __('Transactions') }}
        </flux:button>
    </div>

    {{-- Allocations Table --}}
    @if($view === 'allocations')
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
            <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-purple-100 dark:bg-purple-900/30">
                        <i class="fa-duotone fa-layer-group size-5 text-purple-600 dark:text-purple-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Capital Allocations') }}</h2>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">{{ __('Allocation No') }}</th>
                            <th class="px-4 py-3">{{ __('Accountant') }}</th>
                            <th class="px-4 py-3">{{ __('Period') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Initial') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Spent') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Remaining') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->allocations as $row)
                            @php
                                $remaining = $row->initial_amount - $row->spent_amount;
                                $usagePercent = $row->initial_amount > 0 ? ($row->spent_amount / $row->initial_amount) * 100 : 0;
                                $statusEnum = $row->status instanceof \App\Enums\CapitalAllocationStatus
                                    ? $row->status
                                    : \App\Enums\CapitalAllocationStatus::tryFrom($row->status);
                            @endphp
                            <tr class="text-sm text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $row->allocation_no }}</td>
                                <td class="px-4 py-3">{{ $row->accountant_name ?? '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs">
                                    {{ \Carbon\Carbon::parse($row->starts_on)->format('M d') }} -
                                    {{ \Carbon\Carbon::parse($row->ends_on)->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ money_tzs($row->initial_amount) }}</td>
                                <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">{{ money_tzs($row->spent_amount) }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ money_tzs($remaining) }}</td>
                                <td class="px-4 py-3">
                                    @if($statusEnum)
                                        <flux:badge color="{{ $statusEnum->color() }}" size="sm">{{ $statusEnum->label() }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">{{ ucfirst($row->status ?? 'unknown') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('capital.show', $row->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                        {{ __('View') }}
                                    </a>
                                </td>
                            </tr>
                            {{-- Usage Progress Bar --}}
                            <tr class="bg-zinc-50/50 dark:bg-zinc-800/30">
                                <td colspan="8" class="px-4 py-2">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400 w-20">{{ __('Usage:') }}</span>
                                        <div class="flex-1 h-2 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full {{ $usagePercent > 90 ? 'bg-red-500' : ($usagePercent > 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min($usagePercent, 100) }}%"></div>
                                        </div>
                                        <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 w-12 text-right">{{ number_format($usagePercent, 1) }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-16 text-center">
                                    <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
                                        <i class="fa-duotone fa-layer-group size-7 text-zinc-400 dark:text-zinc-500"></i>
                                    </div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No allocations found') }}</p>
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Try adjusting the filters.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->allocations->hasPages())
                <div class="px-5 py-3 border-t border-zinc-100 dark:border-zinc-700/50">
                    {{ $this->allocations->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- Transactions Table --}}
    @if($view === 'transactions')
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
            <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30">
                        <i class="fa-duotone fa-list-timeline size-5 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Capital Transactions') }}</h2>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">{{ __('Date') }}</th>
                            <th class="px-4 py-3">{{ __('Allocation') }}</th>
                            <th class="px-4 py-3">{{ __('Type') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                            <th class="px-4 py-3">{{ __('Reference') }}</th>
                            <th class="px-4 py-3">{{ __('Description') }}</th>
                            <th class="px-4 py-3">{{ __('Created By') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->transactions as $row)
                            @php
                                $refType = $row->reference_type ? class_basename($row->reference_type) : '';
                                $typeEnum = $row->type instanceof \App\Enums\CapitalTransactionType
                                    ? $row->type
                                    : \App\Enums\CapitalTransactionType::tryFrom($row->type);
                                $isDebit = $typeEnum?->value === 'debit' || $row->type === 'debit';
                            @endphp
                            <tr class="text-sm text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y H:i') }}
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('capital.show', $row->capital_allocation_id) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        {{ $row->allocation_no }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge color="{{ $isDebit ? 'red' : 'emerald' }}" size="sm">
                                        {{ $typeEnum?->label() ?? ucfirst($row->type ?? 'unknown') }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-right font-medium {{ $isDebit ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ $isDebit ? '-' : '+' }}{{ money_tzs($row->amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($refType)
                                        <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-700 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $refType }}</span>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 max-w-xs truncate" title="{{ $row->description }}">
                                    {{ $row->description ?? '-' }}
                                </td>
                                <td class="px-4 py-3">{{ $row->created_by_name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
                                        <i class="fa-duotone fa-list-timeline size-7 text-zinc-400 dark:text-zinc-500"></i>
                                    </div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No transactions found') }}</p>
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Try adjusting the date range or filters.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->transactions->hasPages())
                <div class="px-5 py-3 border-t border-zinc-100 dark:border-zinc-700/50">
                    {{ $this->transactions->links() }}
                </div>
            @endif
        </div>
    @endif
</flux:main>

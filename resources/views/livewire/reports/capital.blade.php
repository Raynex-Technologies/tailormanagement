<flux:main class="p-6">
    {{-- Breadcrumbs --}}
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
            <flux:breadcrumbs.item href="{{ route('reports.index') }}" wire:navigate>Reports</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Capital Audit Report</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- Header --}}
    <flux:card class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">Capital Audit Report</flux:heading>
                <flux:text class="mt-1">Capital allocations, spending, and transactions.</flux:text>
            </div>
            @can('reports.export')
                <div class="flex gap-2">
                    <flux:button wire:click="exportAllocations" variant="ghost" size="sm">
                        <flux:icon name="arrow-down-tray" class="mr-1 size-4" />
                        Export Allocations
                    </flux:button>
                    <flux:button wire:click="exportTransactions" variant="primary" size="sm">
                        <flux:icon name="arrow-down-tray" class="mr-1 size-4" />
                        Export Transactions
                    </flux:button>
                </div>
            @endcan
        </div>
    </flux:card>

    {{-- Filters --}}
    <flux:card class="mb-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input
                wire:model.live.debounce.300ms="dateFrom"
                type="date"
                label="Transaction From"
            />
            <flux:input
                wire:model.live.debounce.300ms="dateTo"
                type="date"
                label="Transaction To"
            />
            <flux:select wire:model.live="accountantId" label="Accountant">
                <option value="">All Accountants</option>
                @foreach($this->accountants as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="status" label="Status">
                <option value="">All Statuses</option>
                @foreach($this->statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </flux:select>
            <div class="flex items-end">
                <flux:button wire:click="resetFilters" variant="ghost" size="sm">
                    <flux:icon name="x-mark" class="mr-1 size-4" />
                    Reset
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Allocated</flux:text>
            <flux:heading size="xl" class="mt-1 text-blue-600 dark:text-blue-400">
                {{ money_tzs($this->summary['total_allocated']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Spent</flux:text>
            <flux:heading size="xl" class="mt-1 text-red-600 dark:text-red-400">
                {{ money_tzs($this->summary['total_spent']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Remaining</flux:text>
            <flux:heading size="xl" class="mt-1 text-emerald-600 dark:text-emerald-400">
                {{ money_tzs($this->summary['total_remaining']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Open Allocations</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ number_format($this->summary['open_allocations_count']) }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Period Stats --}}
    <div class="grid gap-4 sm:grid-cols-2 mb-6">
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                Transactions in Period ({{ \Carbon\Carbon::parse($dateFrom)->format('M d') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }})
            </flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ number_format($this->summary['transaction_count']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Debits in Period</flux:text>
            <flux:heading size="xl" class="mt-1 text-red-600 dark:text-red-400">
                {{ money_tzs($this->summary['period_debits']) }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- View Tabs --}}
    <div class="flex gap-2 mb-4">
        <flux:button
            wire:click="setView('allocations')"
            variant="{{ $view === 'allocations' ? 'primary' : 'ghost' }}"
            size="sm"
        >
            Allocations
        </flux:button>
        <flux:button
            wire:click="setView('transactions')"
            variant="{{ $view === 'transactions' ? 'primary' : 'ghost' }}"
            size="sm"
        >
            Transactions
        </flux:button>
    </div>

    {{-- Allocations Table --}}
    @if($view === 'allocations')
        <flux:card>
            <flux:heading size="lg" class="mb-4">Capital Allocations</flux:heading>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">Allocation No</th>
                            <th class="px-4 py-3">Accountant</th>
                            <th class="px-4 py-3">Period</th>
                            <th class="px-4 py-3 text-right">Initial</th>
                            <th class="px-4 py-3 text-right">Spent</th>
                            <th class="px-4 py-3 text-right">Remaining</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->allocations as $row)
                            @php
                                $remaining = $row->initial_amount - $row->spent_amount;
                                $usagePercent = $row->initial_amount > 0 ? ($row->spent_amount / $row->initial_amount) * 100 : 0;
                            @endphp
                            <tr class="text-sm text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-3 whitespace-nowrap font-medium">
                                    {{ $row->allocation_no }}
                                </td>
                                <td class="px-4 py-3">{{ $row->accountant_name ?? '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs">
                                    {{ \Carbon\Carbon::parse($row->starts_on)->format('M d') }} -
                                    {{ \Carbon\Carbon::parse($row->ends_on)->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ money_tzs($row->initial_amount) }}</td>
                                <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">{{ money_tzs($row->spent_amount) }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ money_tzs($remaining) }}</td>
                                <td class="px-4 py-3">
                                    @php
                                    $statusEnum = $row->status instanceof \App\Enums\CapitalAllocationStatus
                                        ? $row->status
                                        : \App\Enums\CapitalAllocationStatus::tryFrom($row->status);
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusEnum?->value === 'open' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }}">
                                        {{ $statusEnum?->label() ?? ucfirst($row->status ?? 'unknown') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('capital.show', $row->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm">
                                        View
                                    </a>
                                </td>
                            </tr>
                            {{-- Usage Progress Bar --}}
                            <tr class="bg-zinc-50 dark:bg-zinc-800/30">
                                <td colspan="8" class="px-4 py-2">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400 w-20">Usage:</span>
                                        <div class="flex-1 h-2 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            <div class="h-full {{ $usagePercent > 90 ? 'bg-red-500' : ($usagePercent > 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min($usagePercent, 100) }}%"></div>
                                        </div>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400 w-12 text-right">{{ number_format($usagePercent, 1) }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                    No allocations found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->allocations->hasPages())
                <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    {{ $this->allocations->links() }}
                </div>
            @endif
        </flux:card>
    @endif

    {{-- Transactions Table --}}
    @if($view === 'transactions')
        <flux:card>
            <flux:heading size="lg" class="mb-4">Capital Transactions</flux:heading>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Allocation</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Created By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->transactions as $row)
                            @php
                                $refType = $row->reference_type ? class_basename($row->reference_type) : '';
                            @endphp
                            @php
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
                                    <a href="{{ route('capital.show', $row->capital_allocation_id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        {{ $row->allocation_no }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $isDebit ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' }}">
                                        {{ $typeEnum?->label() ?? ucfirst($row->type ?? 'unknown') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium {{ $isDebit ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ $isDebit ? '-' : '+' }}{{ money_tzs($row->amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($refType)
                                        <span class="text-xs bg-zinc-100 dark:bg-zinc-700 rounded px-1.5 py-0.5">{{ $refType }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 max-w-xs truncate" title="{{ $row->description }}">
                                    {{ $row->description ?? '-' }}
                                </td>
                                <td class="px-4 py-3">{{ $row->created_by_name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                    No transactions found for the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->transactions->hasPages())
                <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    {{ $this->transactions->links() }}
                </div>
            @endif
        </flux:card>
    @endif
</flux:main>

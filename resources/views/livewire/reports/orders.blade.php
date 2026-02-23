<flux:main class="p-6">
    {{-- Breadcrumbs --}}
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
            <flux:breadcrumbs.item href="{{ route('reports.index') }}" wire:navigate>Reports</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Orders Report</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- Header --}}
    <flux:card class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">Orders Report</flux:heading>
                <flux:text class="mt-1">Order status, turnaround time, and value analysis.</flux:text>
            </div>
            @can('reports.export')
                <flux:button wire:click="export" variant="primary" size="sm">
                    <x-icon name="download" class="mr-1 size-4" />
                    Export CSV
                </flux:button>
            @endcan
        </div>
    </flux:card>

    {{-- Filters --}}
    <flux:card class="mb-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <flux:input
                wire:model.blur="dateFrom"
                type="date"
                label="From Date"
            />
            <flux:input
                wire:model.blur="dateTo"
                type="date"
                label="To Date"
            />
            <flux:select wire:model.blur="status" label="Status">
                <option value="">All Statuses</option>
                @foreach($this->statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.blur="paymentStatus" label="Payment Status">
                <option value="">All Payment Statuses</option>
                @foreach($this->paymentStatuses as $ps)
                    <option value="{{ $ps->value }}">{{ $ps->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.blur="tailorId" label="Tailor">
                <option value="">All Tailors</option>
                @foreach($this->tailors as $tailor)
                    <option value="{{ $tailor->id }}">{{ $tailor->name }}</option>
                @endforeach
            </flux:select>
            <div class="flex items-end gap-2">
                <flux:input
                    wire:model.blur="search"
                    placeholder="Search..."
                    icon="magnifying-glass"
                    class="flex-1"
                />
                <flux:button wire:click="resetFilters" variant="ghost" size="sm">
                    <x-icon name="close" class="size-4" />
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Orders</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ number_format($this->summary['total_orders']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Completed</flux:text>
            <flux:heading size="xl" class="mt-1 text-emerald-600 dark:text-emerald-400">
                {{ number_format($this->summary['completed_count']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Value</flux:text>
            <flux:heading size="xl" class="mt-1 text-blue-600 dark:text-blue-400">
                {{ money_tzs($this->summary['total_value']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Avg Turnaround</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ $this->summary['avg_turnaround_days'] }} days
            </flux:heading>
        </flux:card>
    </div>

    {{-- Data Table --}}
    <flux:card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">Order No</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3">Due Date</th>
                        <th class="px-4 py-3">Tailor</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Paid</th>
                        <th class="px-4 py-3 text-right">Balance</th>
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
                                <a href="{{ route('orders.show', $row->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    {{ $row->order_no }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $row->customer_name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $row->status->color() }}">
                                    {{ $row->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row->created_at->format('M d, Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($row->due_date)
                                    <span class="{{ $row->due_date->isPast() && !in_array($row->status, [\App\Enums\OrderStatus::Completed, \App\Enums\OrderStatus::Delivered, \App\Enums\OrderStatus::Cancelled]) ? 'text-red-600 dark:text-red-400' : '' }}">
                                        {{ $row->due_date->format('M d, Y') }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $row->tailor_name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ money_tzs($row->total) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ money_tzs($paidAmount) }}</td>
                            <td class="px-4 py-3 text-right {{ $balance > 0 ? 'text-red-600 dark:text-red-400' : '' }}">
                                {{ money_tzs($balance) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                No orders found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->rows->hasPages())
            <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                {{ $this->rows->links() }}
            </div>
        @endif
    </flux:card>
</flux:main>

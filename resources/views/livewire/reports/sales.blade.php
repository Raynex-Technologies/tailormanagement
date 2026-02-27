<flux:main class="p-6">
    {{-- Breadcrumbs --}}
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
            <flux:breadcrumbs.item href="{{ route('reports.index') }}" wire:navigate>Reports</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Sales Report</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- Header --}}
    <flux:card class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">Sales Report</flux:heading>
                <flux:text class="mt-1">Payment transactions and revenue analysis.</flux:text>
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
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
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
            <flux:select wire:model.blur="method" label="Payment Method">
                <option value="">All Methods</option>
                @foreach($this->paymentMethods as $m)
                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                @endforeach
            </flux:select>
            <flux:input
                wire:model.blur="search"
                placeholder="Search order/customer..."
                label="Search"
                icon="magnifying-glass"
            />
            <div class="flex items-end">
                <flux:button wire:click="resetFilters" variant="ghost" size="sm">
                    <x-icon name="close" class="mr-1 size-4" />
                    Reset
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Received</flux:text>
            <flux:heading size="xl" class="mt-1 text-emerald-600 dark:text-emerald-400">
                {{ money_tzs($this->summary['total_received']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Number of Payments</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ number_format($this->summary['total_count']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Average Payment</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ money_tzs($this->summary['avg_payment']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Top Method</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ $this->summary['top_method'] }}
            </flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ money_tzs($this->summary['top_method_amount']) }}</flux:text>
        </flux:card>
    </div>

    {{-- Data Table --}}
    <flux:card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">Order Date</th>
                        <th class="px-4 py-3">Order No</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Received By</th>
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
                                <a href="{{ route('orders.show', $row->order_id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
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
                            <td colspan="7" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                No payments found for the selected filters.
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

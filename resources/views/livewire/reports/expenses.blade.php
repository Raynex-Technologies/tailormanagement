<flux:main class="p-6">
    {{-- Breadcrumbs --}}
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
            <flux:breadcrumbs.item href="{{ route('reports.index') }}" wire:navigate>Reports</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Expenses Report</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- Header --}}
    <flux:card class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">Expenses Report</flux:heading>
                <flux:text class="mt-1">Expense tracking by category and capital allocation.</flux:text>
            </div>
            @can('reports.export')
                <flux:button wire:click="export" variant="primary" size="sm">
                    <flux:icon name="arrow-down-tray" class="mr-1 size-4" />
                    Export CSV
                </flux:button>
            @endcan
        </div>
    </flux:card>

    {{-- Filters --}}
    <flux:card class="mb-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <flux:input
                wire:model.live.debounce.300ms="dateFrom"
                type="date"
                label="From Date"
            />
            <flux:input
                wire:model.live.debounce.300ms="dateTo"
                type="date"
                label="To Date"
            />
            <flux:select wire:model.live="categoryId" label="Category">
                <option value="">All Categories</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="linkedToCapital" label="Capital Linked">
                <option value="">All</option>
                <option value="1">Yes - Linked</option>
                <option value="0">No - Not Linked</option>
            </flux:select>
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search vendor..."
                label="Search"
                icon="magnifying-glass"
            />
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
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Expenses</flux:text>
            <flux:heading size="xl" class="mt-1 text-red-600 dark:text-red-400">
                {{ money_tzs($this->summary['total_expenses']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Number of Expenses</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ number_format($this->summary['total_count']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Linked to Capital</flux:text>
            <flux:heading size="xl" class="mt-1 text-purple-600 dark:text-purple-400">
                {{ money_tzs($this->summary['linked_to_capital_total']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Top Category</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ $this->summary['top_category'] }}
            </flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ money_tzs($this->summary['top_category_amount']) }}</flux:text>
        </flux:card>
    </div>

    {{-- Category Breakdown --}}
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4">Breakdown by Category</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @forelse($this->categoryBreakdown as $cat)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-zinc-900 dark:text-white">{{ $cat->category_name }}</span>
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $cat->count }} items</span>
                    </div>
                    <div class="mt-2 text-lg font-semibold text-red-600 dark:text-red-400">
                        {{ money_tzs($cat->total) }}
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center text-zinc-500 dark:text-zinc-400">
                    No expenses found.
                </div>
            @endforelse
        </div>
    </flux:card>

    {{-- Data Table --}}
    <flux:card>
        <flux:heading size="lg" class="mb-4">Expense Details</flux:heading>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Vendor</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Capital Allocation</th>
                        <th class="px-4 py-3">Created By</th>
                        <th class="px-4 py-3">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->rows as $row)
                        <tr class="text-sm text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($row->expense_date)->format('M d, Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-700 px-2 py-0.5 text-xs font-medium">
                                    {{ $row->category_name ?? 'Uncategorized' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $row->vendor ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-medium text-red-600 dark:text-red-400">
                                {{ money_tzs($row->amount) }}
                            </td>
                            <td class="px-4 py-3">
                                @if($row->allocation_no)
                                    <a href="{{ route('capital.show', $row->capital_allocation_id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        {{ $row->allocation_no }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $row->created_by_name ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $row->reference ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                No expenses found for the selected filters.
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

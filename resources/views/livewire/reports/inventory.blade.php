<flux:main class="p-6">
    {{-- Breadcrumbs --}}
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
            <flux:breadcrumbs.item href="{{ route('reports.index') }}" wire:navigate>Reports</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Inventory Report</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- Header --}}
    <flux:card class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">Inventory Report</flux:heading>
                <flux:text class="mt-1">Stock levels, movements, and low stock alerts.</flux:text>
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
                label="Movement From"
            />
            <flux:input
                wire:model.live.debounce.300ms="dateTo"
                type="date"
                label="Movement To"
            />
            <flux:select wire:model.live="categoryId" label="Category">
                <option value="">All Categories</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </flux:select>
            <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="lowStockOnly" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-zinc-700 dark:text-zinc-300">Low Stock Only</span>
                </label>
            </div>
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search SKU/name..."
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
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6">
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Items</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ number_format($this->summary['total_items']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Low Stock</flux:text>
            <flux:heading size="xl" class="mt-1 {{ $this->summary['low_stock_count'] > 0 ? 'text-red-600 dark:text-red-400' : '' }}">
                {{ number_format($this->summary['low_stock_count']) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total On Hand</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ number_format($this->summary['total_on_hand'], 2) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Received (Period)</flux:text>
            <flux:heading size="xl" class="mt-1 text-emerald-600 dark:text-emerald-400">
                {{ number_format($this->summary['total_received'], 2) }}
            </flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Issued (Period)</flux:text>
            <flux:heading size="xl" class="mt-1 text-amber-600 dark:text-amber-400">
                {{ number_format($this->summary['total_issued'], 2) }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- View Tabs --}}
    <div class="flex gap-2 mb-4">
        <flux:button
            wire:click="setView('stock')"
            variant="{{ $view === 'stock' ? 'primary' : 'ghost' }}"
            size="sm"
        >
            Current Stock Levels
        </flux:button>
        <flux:button
            wire:click="setView('movement')"
            variant="{{ $view === 'movement' ? 'primary' : 'ghost' }}"
            size="sm"
        >
            Movement Summary
        </flux:button>
    </div>

    {{-- Stock Levels Table --}}
    @if($view === 'stock')
        <flux:card>
            <flux:heading size="lg" class="mb-4">Current Stock Levels</flux:heading>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-right">On Hand</th>
                            <th class="px-4 py-3 text-right">Reserved</th>
                            <th class="px-4 py-3 text-right">Reorder Level</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->stockLevels as $row)
                            @php
                                $isLowStock = $row->qty_on_hand <= $row->reorder_level;
                            @endphp
                            <tr class="text-sm text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 {{ $isLowStock ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                                <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $row->sku }}</td>
                                <td class="px-4 py-3">{{ $row->item_name }}</td>
                                <td class="px-4 py-3">{{ $row->category_name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format($row->qty_on_hand, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->qty_reserved, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->reorder_level, 2) }}</td>
                                <td class="px-4 py-3">
                                    @if($isLowStock)
                                        <span class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/30 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-400">
                                            Low Stock
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                            OK
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                    No stock items found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->stockLevels->hasPages())
                <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    {{ $this->stockLevels->links() }}
                </div>
            @endif
        </flux:card>
    @endif

    {{-- Movement Summary Table --}}
    @if($view === 'movement')
        <flux:card>
            <flux:heading size="lg" class="mb-4">Movement Summary ({{ \Carbon\Carbon::parse($dateFrom)->format('M d') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }})</flux:heading>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3 text-right">Received</th>
                            <th class="px-4 py-3 text-right">Issued</th>
                            <th class="px-4 py-3 text-right">Adjusted</th>
                            <th class="px-4 py-3">Last Movement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->movementSummary as $row)
                            <tr class="text-sm text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $row->sku }}</td>
                                <td class="px-4 py-3">{{ $row->name }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">
                                    {{ $row->received_qty > 0 ? '+' . number_format($row->received_qty, 2) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">
                                    {{ $row->issued_qty > 0 ? '-' . number_format($row->issued_qty, 2) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right {{ $row->adjusted_qty > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($row->adjusted_qty < 0 ? 'text-red-600 dark:text-red-400' : '') }}">
                                    {{ $row->adjusted_qty != 0 ? ($row->adjusted_qty > 0 ? '+' : '') . number_format($row->adjusted_qty, 2) : '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-zinc-500">
                                    {{ $row->last_movement_at ? \Carbon\Carbon::parse($row->last_movement_at)->format('M d, Y H:i') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                    No movements found for the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->movementSummary->hasPages())
                <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    {{ $this->movementSummary->links() }}
                </div>
            @endif
        </flux:card>
    @endif
</flux:main>

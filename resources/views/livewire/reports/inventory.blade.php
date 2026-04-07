<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
        <flux:breadcrumbs.item href="{{ route('reports.index') }}" wire:navigate>{{ __('Reports') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Inventory Report') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Header --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex items-center justify-center size-12 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                    <i class="fa-duotone fa-box-archive size-6 text-amber-600 dark:text-amber-400"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Inventory Report') }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Stock levels, movements, and low stock alerts.') }}</p>
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
        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
            <flux:input wire:model.blur="dateFrom" type="date" label="{{ __('Movement From') }}" />
            <flux:input wire:model.blur="dateTo" type="date" label="{{ __('Movement To') }}" />
            <flux:select wire:model.blur="categoryId" label="{{ __('Category') }}">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </flux:select>
            <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.blur="lowStockOnly" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500 dark:border-zinc-600 dark:bg-zinc-800">
                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Low Stock Only') }}</span>
                </label>
            </div>
            <flux:input wire:model.blur="search" placeholder="{{ __('Search SKU/name...') }}" label="{{ __('Search') }}" icon="magnifying-glass" />
            <div class="flex items-end">
                <flux:button wire:click="resetFilters" variant="ghost" size="sm">
                    <i class="fa-duotone fa-xmark mr-1 size-4"></i>
                    {{ __('Reset') }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 mb-3">
                <i class="fa-duotone fa-boxes-stacked size-5 text-indigo-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($this->summary['total_items']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Items') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-red-50 dark:bg-red-900/30 mb-3">
                <i class="fa-duotone fa-triangle-exclamation size-5 text-red-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight {{ $this->summary['low_stock_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-white' }}">{{ number_format($this->summary['low_stock_count']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Low Stock') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-blue-50 dark:bg-blue-900/30 mb-3">
                <i class="fa-duotone fa-warehouse size-5 text-blue-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($this->summary['total_on_hand'], 2) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total On Hand') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 mb-3">
                <i class="fa-duotone fa-arrow-down-to-line size-5 text-emerald-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ number_format($this->summary['total_received'], 2) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Received (Period)') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-amber-50 dark:bg-amber-900/30 mb-3">
                <i class="fa-duotone fa-arrow-up-from-line size-5 text-amber-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-amber-600 dark:text-amber-400">{{ number_format($this->summary['total_issued'], 2) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Issued (Period)') }}</p>
        </div>
    </div>

    {{-- View Tabs --}}
    <div class="flex gap-2">
        <flux:button wire:click="setView('stock')" variant="{{ $view === 'stock' ? 'primary' : 'ghost' }}" size="sm">
            <i class="fa-duotone fa-cubes mr-1.5 size-4"></i>
            {{ __('Current Stock Levels') }}
        </flux:button>
        <flux:button wire:click="setView('movement')" variant="{{ $view === 'movement' ? 'primary' : 'ghost' }}" size="sm">
            <i class="fa-duotone fa-arrows-left-right mr-1.5 size-4"></i>
            {{ __('Movement Summary') }}
        </flux:button>
    </div>

    {{-- Stock Levels Table --}}
    @if($view === 'stock')
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
            <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                        <i class="fa-duotone fa-cubes size-5 text-amber-600 dark:text-amber-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Current Stock Levels') }}</h2>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar-light">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">{{ __('SKU') }}</th>
                            <th class="px-4 py-3">{{ __('Item') }}</th>
                            <th class="px-4 py-3">{{ __('Category') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('On Hand') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Reserved') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Reorder Level') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->stockLevels as $row)
                            @php $isLowStock = $row->qty_on_hand <= $row->reorder_level; @endphp
                            <tr class="text-sm text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 {{ $isLowStock ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                                <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $row->sku }}</td>
                                <td class="px-4 py-3">{{ $row->item_name }}</td>
                                <td class="px-4 py-3">{{ $row->category_name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format($row->qty_on_hand, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->qty_reserved, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->reorder_level, 2) }}</td>
                                <td class="px-4 py-3">
                                    @if($isLowStock)
                                        <flux:badge color="red" size="sm">{{ __('Low Stock') }}</flux:badge>
                                    @else
                                        <flux:badge color="emerald" size="sm">{{ __('OK') }}</flux:badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
                                        <i class="fa-duotone fa-boxes-stacked size-7 text-zinc-400 dark:text-zinc-500"></i>
                                    </div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No stock items found') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->stockLevels->hasPages())
                <div class="px-5 py-3 border-t border-zinc-100 dark:border-zinc-700/50">
                    {{ $this->stockLevels->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- Movement Summary Table --}}
    @if($view === 'movement')
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
            <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-blue-100 dark:bg-blue-900/30">
                        <i class="fa-duotone fa-arrows-left-right size-5 text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Movement Summary') }} ({{ \Carbon\Carbon::parse($dateFrom)->format('M d') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }})</h2>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar-light">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">{{ __('SKU') }}</th>
                            <th class="px-4 py-3">{{ __('Item') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Received') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Issued') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Adjusted') }}</th>
                            <th class="px-4 py-3">{{ __('Last Movement') }}</th>
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
                                <td colspan="6" class="px-4 py-16 text-center">
                                    <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
                                        <i class="fa-duotone fa-arrows-left-right size-7 text-zinc-400 dark:text-zinc-500"></i>
                                    </div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No movements found') }}</p>
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Try adjusting the date range.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->movementSummary->hasPages())
                <div class="px-5 py-3 border-t border-zinc-100 dark:border-zinc-700/50">
                    {{ $this->movementSummary->links() }}
                </div>
            @endif
        </div>
    @endif
</flux:main>

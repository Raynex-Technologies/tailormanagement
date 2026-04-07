<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" wire:navigate icon="home" />
        <flux:breadcrumbs.item href="{{ route('reports.index') }}" wire:navigate>{{ __('Reports') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Expenses Report') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Header --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-6 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex items-center justify-center size-12 rounded-xl bg-red-100 dark:bg-red-900/30">
                    <i class="fa-duotone fa-receipt size-6 text-red-600 dark:text-red-400"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Expenses Report') }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Expense tracking by category, order expenses, and capital allocation.') }}</p>
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
        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-7">
            <flux:input wire:model.live="dateFrom" type="date" label="{{ __('From Date') }}" />
            <flux:input wire:model.live="dateTo" type="date" label="{{ __('To Date') }}" />
            <flux:select wire:model.live="categoryId" label="{{ __('Category') }}">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="subcategoryId" label="{{ __('Subcategory') }}" :disabled="$categoryId === 'order_expenses'">
                <option value="">{{ __('All Subcategories') }}</option>
                @foreach($this->subcategories as $subcat)
                    <option value="{{ $subcat->id }}">{{ $subcat->name }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="linkedToCapital" label="{{ __('Capital Linked') }}">
                <option value="">{{ __('All') }}</option>
                <option value="1">{{ __('Yes - Linked') }}</option>
                <option value="0">{{ __('No - Not Linked') }}</option>
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search vendor, reference, or notes...') }}" label="{{ __('Search') }}" icon="magnifying-glass" />
            <div class="flex items-end">
                <flux:button wire:click="resetFilters" variant="ghost" size="sm">
                    <i class="fa-duotone fa-xmark mr-1 size-4"></i>
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-red-50 dark:bg-red-900/30 mb-3">
                <i class="fa-duotone fa-receipt size-5 text-red-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-red-600 dark:text-red-400">{{ money_tzs($this->summary['total_expenses']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Expenses') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 mb-3">
                <i class="fa-duotone fa-hashtag size-5 text-indigo-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($this->summary['total_count']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Number of Expenses') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-violet-50 dark:bg-violet-900/30 mb-3">
                <i class="fa-duotone fa-building-columns size-5 text-violet-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-violet-600 dark:text-violet-400">{{ money_tzs($this->summary['linked_to_capital_total']) }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Linked to Capital') }}</p>
        </div>

        <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex items-center justify-center size-11 rounded-xl bg-amber-50 dark:bg-amber-900/30 mb-3">
                <i class="fa-duotone fa-trophy size-5 text-amber-500"></i>
            </div>
            <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->summary['top_category'] }}</p>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Top Category') }} &middot; {{ money_tzs($this->summary['top_category_amount']) }}</p>
        </div>
    </div>

    {{-- Category Breakdown --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
        <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-10 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                    <i class="fa-duotone fa-chart-pie size-5 text-amber-600 dark:text-amber-400"></i>
                </div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Breakdown by Category') }}</h2>
            </div>
        </div>
        <div class="p-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($this->categoryBreakdown as $cat)
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $cat->category_name }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $cat->count }} {{ __('items') }}</span>
                        </div>
                        <div class="mt-2 text-lg font-semibold text-red-600 dark:text-red-400">
                            {{ money_tzs($cat->total) }}
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-8 text-center">
                        <div class="flex items-center justify-center size-12 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
                            <i class="fa-duotone fa-chart-pie size-6 text-zinc-400 dark:text-zinc-500"></i>
                        </div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No expenses found.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Data Table --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
        <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-10 rounded-xl bg-red-100 dark:bg-red-900/30">
                    <i class="fa-duotone fa-table-list size-5 text-red-600 dark:text-red-400"></i>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Expense Details') }}</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Individual expense records for the selected period') }}</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar-light">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Category') }}</th>
                        <th class="px-4 py-3">{{ __('Subcategory') }}</th>
                        <th class="px-4 py-3">{{ __('Vendor') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                        <th class="px-4 py-3">{{ __('Capital Allocation') }}</th>
                        <th class="px-4 py-3">{{ __('Created By') }}</th>
                        <th class="px-4 py-3">{{ __('Reference') }}</th>
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
                                    {{ $row->category_name ?? __('Uncategorized') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $row->subcategory_name ?? '-' }}</td>
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
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $row->created_by_name ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $row->reference ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center">
                                <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
                                    <i class="fa-duotone fa-receipt size-7 text-zinc-400 dark:text-zinc-500"></i>
                                </div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No expenses found') }}</p>
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

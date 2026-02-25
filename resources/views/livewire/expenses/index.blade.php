<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Expenses') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-circle">
            {{ session('error') }}
        </flux:callout>
    @endif

    {{-- Header --}}
    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Expenses') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Track and manage business expenses.') }}</flux:text>
            </div>
            @can('expenses.manage')
                <flux:button :href="route('expenses.create')" wire:navigate>
                    <x-icon name="add" class="mr-1 size-4" />
                    {{ __('New Expense') }}
                </flux:button>
            @endcan
        </div>
    </flux:card>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Current Month') }}</flux:text>
            <flux:heading size="lg" class="mt-1 font-mono text-amber-600 dark:text-amber-400">
                {{ money_tzs($currentMonthStats['total']) }}
            </flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ $currentMonthStats['count'] }} {{ __('expenses') }}</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Filtered Period') }}</flux:text>
            <flux:heading size="lg" class="mt-1 font-mono text-indigo-600 dark:text-indigo-400">
                {{ money_tzs($stats['total_amount']) }}
            </flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ $stats['count'] }} {{ __('expenses') }}</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Linked to Capital') }}</flux:text>
            <flux:heading size="lg" class="mt-1 text-green-600 dark:text-green-400">
                {{ $stats['linked_to_capital'] }}
            </flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ __('expenses') }}</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Categories') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ $categories->count() }}
            </flux:heading>
            @can('expenses.categories.manage')
                <a href="{{ route('expenses.categories.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                    {{ __('Manage') }}
                </a>
            @endcan
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
            {{-- Search --}}
            <div class="w-full lg:w-1/4">
                <flux:label for="search">{{ __('Search') }}</flux:label>
                <flux:input id="search" wire:model.live.debounce.300ms="search" placeholder="Vendor, reference, note..." icon="magnifying-glass" />
            </div>

            {{-- Category --}}
            <div class="w-full lg:w-1/5">
                <flux:label for="categoryFilter">{{ __('Category') }}</flux:label>
                <flux:select id="categoryFilter" wire:model.live="categoryFilter">
                    <flux:select.option value="">{{ __('All Categories') }}</flux:select.option>
                    @foreach ($categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Linked to Capital --}}
            <div class="w-full lg:w-1/5">
                <flux:label for="linkedToCapitalFilter">{{ __('Capital Linked') }}</flux:label>
                <flux:select id="linkedToCapitalFilter" wire:model.live="linkedToCapitalFilter">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    <flux:select.option value="yes">{{ __('Yes') }}</flux:select.option>
                    <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                </flux:select>
            </div>

            {{-- Date From --}}
            <div class="w-full lg:w-1/6">
                <flux:label for="dateFrom">{{ __('From') }}</flux:label>
                <flux:input type="date" id="dateFrom" wire:model.live="dateFrom" />
            </div>

            {{-- Date To --}}
            <div class="w-full lg:w-1/6">
                <flux:label for="dateTo">{{ __('To') }}</flux:label>
                <flux:input type="date" id="dateTo" wire:model.live="dateTo" />
            </div>

            {{-- Clear Filters --}}
            <div>
                <flux:button type="button" size="sm" variant="ghost" wire:click="clearFilters">
                    <x-icon name="close" class="mr-1 size-4" />
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Expenses Table --}}
    <flux:card>
        @if ($expenses->isEmpty())
            <div class="py-12 text-center">
                <x-icon name="receipt" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-4">{{ __('No expenses found') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Create a new expense to get started.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column>{{ __('Vendor') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Capital') }}</flux:table.column>
                    <flux:table.column>{{ __('Created By') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($expenses as $expense)
                        <flux:table.row wire:key="exp-{{ $expense->id }}">
                            <flux:table.cell class="font-medium">
                                {{ $expense->expense_date->format('M d, Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($expense->category)
                                    <flux:badge color="zinc" size="sm">{{ $expense->category->name }}</flux:badge>
                                @else
                                    <span class="text-zinc-400">{{ __('Uncategorized') }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $expense->vendor ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-red-600 dark:text-red-400">
                                {{ money_tzs($expense->amount) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($expense->capitalAllocation)
                                    <a href="{{ route('capital.show', $expense->capitalAllocation) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                                        <flux:badge color="green" size="sm">
                                            {{ $expense->capitalAllocation->allocation_no }}
                                        </flux:badge>
                                    </a>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">
                                {{ $expense->creator?->name ?? 'N/A' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-1">
                                    <flux:button size="xs" variant="ghost" :href="route('expenses.show', $expense)" wire:navigate>
                                        <x-icon name="visibility" class="size-4" />
                                    </flux:button>
                                    @can('update', $expense)
                                        <flux:button size="xs" variant="ghost" :href="route('expenses.edit', $expense)" wire:navigate>
                                            <x-icon name="edit" class="size-4" />
                                        </flux:button>
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:label for="perPage" class="text-sm">{{ __('Show') }}</flux:label>
                    <flux:select id="perPage" wire:model.live="perPage" class="w-20">
                        <flux:select.option value="15">15</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                    </flux:select>
                </div>
                {{ $expenses->links() }}
            </div>
        @endif
    </flux:card>
</flux:main>

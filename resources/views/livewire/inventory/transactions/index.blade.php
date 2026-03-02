<div>
    <flux:main class="p-6">
        <div class="mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
                <flux:breadcrumbs.item :href="route('inventory.stock')" wire:navigate>{{ __('Inventory') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Transactions') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        {{-- Page Header --}}
        <div class="mb-6">
            <flux:heading size="xl">{{ __('Inventory Transactions') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                {{ __('View stock movement history and transaction ledger.') }}
            </flux:text>
        </div>

        {{-- Stats Cards --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card class="p-4">
                <div class="flex items-center gap-4">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                        <x-icon name="download" class="size-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Received</flux:text>
                        <flux:heading size="lg" class="text-green-600 dark:text-green-400">
                            +{{ number_format($stats['received'], 0) }}
                        </flux:heading>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-4">
                <div class="flex items-center gap-4">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                        <x-icon name="upload" class="size-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Issued</flux:text>
                        <flux:heading size="lg" class="text-red-600 dark:text-red-400">
                            -{{ number_format($stats['issued'], 0) }}
                        </flux:heading>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-4">
                <div class="flex items-center gap-4">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                        <x-icon name="tune" class="size-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Adjusted</flux:text>
                        <flux:heading size="lg" class="{{ $stats['adjusted'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $stats['adjusted'] >= 0 ? '+' : '' }}{{ number_format($stats['adjusted'], 0) }}
                        </flux:heading>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-4">
                <div class="flex items-center gap-4">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                        <x-icon name="description" class="size-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Transactions</flux:text>
                        <flux:heading size="lg">{{ number_format($stats['total_transactions']) }}</flux:heading>
                    </div>
                </div>
            </flux:card>
        </div>

        {{-- Filters --}}
        <flux:card class="mb-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <flux:input
                    wire:model.blur="search"
                    placeholder="Search item..."
                    icon="magnifying-glass"
                />

                <flux:select wire:model.blur="typeFilter">
                    <flux:select.option value="">All Types</flux:select.option>
                    @foreach ($transactionTypes as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.blur="itemFilter">
                    <flux:select.option value="">All Items</flux:select.option>
                    @foreach ($items as $id => $name)
                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model.blur="dateFrom"
                    type="date"
                    label=""
                />

                <flux:input
                    wire:model.blur="dateTo"
                    type="date"
                    label=""
                />

                <div class="flex items-center gap-2">
                    <flux:select wire:model.blur="perPage" class="flex-1">
                        <flux:select.option value="15">15</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                    </flux:select>

                    <flux:button size="sm" variant="ghost" wire:click="clearFilters" title="Clear Filters">
                        <x-icon name="close" class="size-4" />
                    </flux:button>
                </div>
            </div>
        </flux:card>

        {{-- Transactions Table --}}
        <flux:card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-sm font-semibold text-zinc-900 dark:text-white">
                            <th class="px-4 py-3">{{ __('Date') }}</th>
                            <th class="px-4 py-3">{{ __('Item') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Type') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Unit Cost') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Total Cost') }}</th>
                            <th class="px-4 py-3">{{ __('Created By') }}</th>
                            <th class="px-4 py-3">{{ __('Note') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($transactions as $transaction)
                            @php
                                $typeColor = match($transaction->type) {
                                    \App\Enums\InventoryTransactionType::Receive => 'green',
                                    \App\Enums\InventoryTransactionType::Issue => 'red',
                                    \App\Enums\InventoryTransactionType::Adjust => 'amber',
                                    \App\Enums\InventoryTransactionType::Return => 'blue',
                                    default => 'zinc',
                                };
                                $qtySign = $transaction->qty >= 0 ? '+' : '';
                                $qtyClass = $transaction->qty >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
                            @endphp
                            <tr class="text-sm text-zinc-700 dark:text-zinc-300" wire:key="txn-{{ $transaction->id }}">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span>{{ $transaction->created_at->format('M d, Y') }}</span>
                                        <span class="text-xs text-zinc-500">{{ $transaction->created_at->format('H:i') }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ $transaction->item?->name ?? 'Deleted Item' }}</span>
                                        <code class="text-xs text-zinc-500">{{ $transaction->item?->sku ?? '-' }}</code>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <flux:badge size="sm" color="{{ $typeColor }}">
                                        {{ $transaction->type->label() }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold {{ $qtyClass }}">
                                    {{ $qtySign }}{{ number_format($transaction->qty, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    @if ($transaction->unit_cost)
                                        {{ number_format($transaction->unit_cost, 2) }}
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    @if ($transaction->total_cost)
                                        {{ number_format($transaction->total_cost, 2) }}
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $transaction->creator?->name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 max-w-xs truncate" title="{{ $transaction->note }}">
                                    {{ $transaction->note ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <x-icon name="description" class="size-12 text-zinc-300 dark:text-zinc-600" />
                                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                                            {{ __('No transactions found for the selected filters.') }}
                                        </flux:text>
                                        <flux:button size="sm" variant="ghost" wire:click="clearFilters">
                                            {{ __('Clear Filters') }}
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($transactions->hasPages())
                <div class="mt-4 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $transactions->links() }}
                </div>
            @endif
        </flux:card>
    </flux:main>
</div>

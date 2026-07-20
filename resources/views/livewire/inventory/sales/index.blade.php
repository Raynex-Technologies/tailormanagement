<flux:main class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Inventory') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Sales') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Sales') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                {{ __('Review POS sales according to your sale visibility permissions.') }}
            </flux:text>
        </div>

        @can('pos.sell')
            <flux:button variant="primary" icon="plus" :href="route('pos.index')" wire:navigate>
                {{ __('New POS Sale') }}
            </flux:button>
        @endcan
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Sales Total') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ money_tzs($summary['total_amount']) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Amount Collected') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ money_tzs($summary['total_paid']) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Sale Count') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($summary['sales_count']) }}</flux:heading>
        </flux:card>
    </div>

    <flux:card>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <flux:input
                wire:model.live.debounce.300ms="search"
                label="{{ __('Search') }}"
                placeholder="{{ __('Sale, customer, reference...') }}"
                icon="magnifying-glass"
                class="lg:col-span-2"
            />
            <flux:input wire:model.live="dateFrom" type="date" label="{{ __('From') }}" />
            <flux:input wire:model.live="dateTo" type="date" label="{{ __('To') }}" />
            <flux:select wire:model.live="paymentMethod" label="{{ __('Method') }}">
                <flux:select.option value="">{{ __('All Methods') }}</flux:select.option>
                @foreach ($paymentMethods as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="cashier" label="{{ __('Cashier') }}">
                <flux:select.option value="">{{ __('All Cashiers') }}</flux:select.option>
                @foreach ($cashiers as $cashierOption)
                    <flux:select.option value="{{ $cashierOption->id }}">{{ $cashierOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="mt-4 flex justify-end">
            <flux:button variant="ghost" size="sm" wire:click="resetFilters">
                {{ __('Reset Filters') }}
            </flux:button>
        </div>
    </flux:card>

    <flux:card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">{{ __('Sale') }}</th>
                        <th class="px-4 py-3">{{ __('Sold At') }}</th>
                        <th class="px-4 py-3">{{ __('Customer') }}</th>
                        <th class="px-4 py-3">{{ __('Cashier') }}</th>
                        <th class="px-4 py-3">{{ __('Branch') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Items') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                        <th class="px-4 py-3">{{ __('Method') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($sales as $sale)
                        <tr class="text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('inventory.sales.show', $sale) }}" wire:navigate class="font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    {{ $sale->sale_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->sold_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $sale->customer?->name ?? __('Customer') }}</td>
                            <td class="px-4 py-3">{{ $sale->user?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $sale->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($sale->items_count) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold">{{ money_tzs($sale->total_amount) }}</td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm">{{ \Illuminate\Support\Str::of((string) $sale->payment_method)->replace('_', ' ')->title() }}</flux:badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center">
                                <x-icon name="payments" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                                <flux:heading size="md" class="mt-3">{{ __('No sales found') }}</flux:heading>
                                <flux:text class="text-zinc-500">{{ __('Try changing the filters or date range.') }}</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sales->hasPages())
            <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                {{ $sales->links() }}
            </div>
        @endif
    </flux:card>
</flux:main>

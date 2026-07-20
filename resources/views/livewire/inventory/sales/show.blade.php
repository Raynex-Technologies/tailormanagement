<flux:main class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
        <flux:breadcrumbs.item :href="route('inventory.sales.index')" wire:navigate>{{ __('Sales') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $sale->sale_number }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $sale->sale_number }}</flux:heading>
            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                {{ __('Sold at') }} {{ $sale->sold_at?->format('M d, Y H:i') ?? '-' }}
            </flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('pos.view')
                <flux:button variant="outline" :href="route('pos.sales.show', $sale)" target="_blank">
                    {{ __('Receipt') }}
                </flux:button>
            @endcan
            <flux:button variant="ghost" :href="route('inventory.sales.index')" wire:navigate>
                {{ __('Back') }}
            </flux:button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Total') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ money_tzs($sale->total_amount) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Amount Paid') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ money_tzs($sale->amount_paid) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Change') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ money_tzs($sale->change_amount) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Method') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ \Illuminate\Support\Str::of((string) $sale->payment_method)->replace('_', ' ')->title() }}</flux:heading>
        </flux:card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <flux:card>
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Items') }}</flux:heading>
                    <flux:badge>{{ $sale->items->count() }} {{ __('item(s)') }}</flux:badge>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('Item') }}</th>
                                <th class="px-4 py-3">{{ __('SKU') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Qty') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Unit') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Discount') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Line Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($sale->items as $item)
                                <tr class="text-sm text-zinc-700 dark:text-zinc-300">
                                    <td class="px-4 py-3 font-medium">{{ $item->item_name }}</td>
                                    <td class="px-4 py-3 text-zinc-500">{{ $item->sku ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td class="px-4 py-3 text-right">{{ money_tzs($item->unit_price) }}</td>
                                    <td class="px-4 py-3 text-right">{{ money_tzs($item->discount_amount) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ money_tzs($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Sale Details') }}</flux:heading>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Customer') }}</dt>
                        <dd class="text-right font-medium">{{ $sale->customer?->name ?? __('Client') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Cashier') }}</dt>
                        <dd class="text-right font-medium">{{ $sale->user?->name ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Branch') }}</dt>
                        <dd class="text-right font-medium">{{ $sale->branch?->name ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Reference') }}</dt>
                        <dd class="text-right font-medium">{{ $sale->payment_reference ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Status') }}</dt>
                        <dd><flux:badge size="sm">{{ \Illuminate\Support\Str::headline((string) $sale->status) }}</flux:badge></dd>
                    </div>
                </dl>
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Totals') }}</flux:heading>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Subtotal') }}</dt>
                        <dd class="font-mono">{{ money_tzs($sale->subtotal) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Discount') }}</dt>
                        <dd class="font-mono">{{ money_tzs($sale->discount_amount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Tax') }}</dt>
                        <dd class="font-mono">{{ money_tzs($sale->tax_amount) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-zinc-200 pt-3 text-base dark:border-zinc-700">
                        <dt class="font-semibold">{{ __('Total') }}</dt>
                        <dd class="font-mono font-bold">{{ money_tzs($sale->total_amount) }}</dd>
                    </div>
                </dl>
            </flux:card>

            @if ($sale->notes)
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Notes') }}</flux:heading>
                    <flux:text>{{ $sale->notes }}</flux:text>
                </flux:card>
            @endif
        </div>
    </div>
</flux:main>

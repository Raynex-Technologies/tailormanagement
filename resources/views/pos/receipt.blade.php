<x-layouts::app :title="__('POS Receipt')">
    <flux:main class="mx-auto max-w-3xl space-y-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
            <flux:breadcrumbs.item :href="route('pos.index')" wire:navigate>{{ __('POS') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $sale->sale_number }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Receipt') }} {{ $sale->sale_number }}</flux:heading>
                <flux:text class="text-zinc-500">{{ $sale->sold_at?->format('M d, Y H:i') }}</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:button :href="route('pos.index')" wire:navigate icon="arrow-left">{{ __('Back to POS') }}</flux:button>
                <flux:button variant="primary" icon="printer" onclick="window.print()">{{ __('Print') }}</flux:button>
            </div>
        </div>

        <flux:card>
            <div class="receipt-print space-y-6">
                <div class="border-b border-zinc-200 pb-4 text-center dark:border-zinc-700">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $settings->business_name ?? config('app.name') }}</h2>
                    @if ($settings->address)
                        <p class="text-sm text-zinc-500">{{ $settings->address }}</p>
                    @endif
                    @if ($settings->phone)
                        <p class="text-sm text-zinc-500">{{ $settings->phone }}</p>
                    @endif
                </div>

                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-zinc-500">{{ __('Sale Number') }}</p>
                        <p class="font-semibold text-zinc-900 dark:text-white">{{ $sale->sale_number }}</p>
                    </div>
                    <div>
                        <p class="text-zinc-500">{{ __('Cashier') }}</p>
                        <p class="font-semibold text-zinc-900 dark:text-white">{{ $sale->user?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-zinc-500">{{ __('Customer') }}</p>
                        <p class="font-semibold text-zinc-900 dark:text-white">{{ $sale->customer?->name ?? __('Walk-in Customer') }}</p>
                    </div>
                    <div>
                        <p class="text-zinc-500">{{ __('Payment') }}</p>
                        <p class="font-semibold text-zinc-900 dark:text-white">
                            {{ str($sale->payment_method)->replace('_', ' ')->title() }}
                            @if ($sale->payment_reference)
                                <span class="text-zinc-500">({{ $sale->payment_reference }})</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                <th class="py-3">{{ __('Item') }}</th>
                                <th class="py-3 text-right">{{ __('Qty') }}</th>
                                <th class="py-3 text-right">{{ __('Unit') }}</th>
                                <th class="py-3 text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($sale->items as $item)
                                <tr>
                                    <td class="py-3">
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ $item->item_name }}</p>
                                        <p class="text-xs text-zinc-500">{{ $item->sku }}</p>
                                    </td>
                                    <td class="py-3 text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td class="py-3 text-right">{{ money_tzs($item->unit_price) }}</td>
                                    <td class="py-3 text-right font-mono">{{ money_tzs($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="ml-auto max-w-sm space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-zinc-500">{{ __('Subtotal') }}</span><span class="font-mono">{{ money_tzs($sale->subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">{{ __('Discount') }}</span><span class="font-mono">-{{ money_tzs($sale->discount_amount) }}</span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">{{ __('Tax') }}</span><span class="font-mono">{{ money_tzs($sale->tax_amount) }}</span></div>
                    <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-bold dark:border-zinc-700"><span>{{ __('Total') }}</span><span class="font-mono">{{ money_tzs($sale->total_amount) }}</span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">{{ __('Amount Paid') }}</span><span class="font-mono">{{ money_tzs($sale->amount_paid) }}</span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">{{ __('Change') }}</span><span class="font-mono">{{ money_tzs($sale->change_amount) }}</span></div>
                </div>
            </div>
        </flux:card>
    </flux:main>

    <style>
        @media print {
            .desktop-sidebar,
            .top-frosted-nav,
            flux\:breadcrumbs,
            button,
            a[data-flux-button] {
                display: none !important;
            }

            .app-main-shell {
                margin-left: 0 !important;
            }

            .app-page-content {
                padding: 0 !important;
            }
        }
    </style>
</x-layouts::app>

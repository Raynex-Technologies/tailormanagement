<div>
    <flux:main class="p-6">
        {{-- Page Header --}}
        <div class="mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
                <flux:breadcrumbs.item :href="route('orders.index')" wire:navigate>Orders</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('orders.show', $deliveryNote->order)" wire:navigate>
                    {{ $deliveryNote->order?->order_no }}
                </flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Delivery Note</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        <flux:card class="mx-auto max-w-3xl">
            {{-- Header --}}
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <flux:heading size="xl">{{ $deliveryNote->delivery_note_no }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                        Delivery Note
                    </flux:text>
                </div>
                <flux:button variant="subtle" :href="route('delivery-notes.print', $deliveryNote)" target="_blank">
                    <x-icon name="print" class="mr-1 size-4" />
                    Print
                </flux:button>
            </div>

            {{-- Delivery Info --}}
            <div class="mb-6 grid gap-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50 sm:grid-cols-2">
                <div>
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">Delivered At</span>
                    <p class="font-medium text-zinc-900 dark:text-white">
                        {{ $deliveryNote->delivered_at->format('M d, Y H:i') }}
                    </p>
                </div>
                <div>
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">Delivered By</span>
                    <p class="font-medium text-zinc-900 dark:text-white">
                        {{ $deliveryNote->deliveredBy?->name ?? 'N/A' }}
                    </p>
                </div>
                @if ($deliveryNote->received_by_name)
                    <div>
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">Received By</span>
                        <p class="font-medium text-zinc-900 dark:text-white">
                            {{ $deliveryNote->received_by_name }}
                        </p>
                    </div>
                @endif
                @if ($deliveryNote->received_by_phone)
                    <div>
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">Receiver Phone</span>
                        <p class="font-medium text-zinc-900 dark:text-white">
                            {{ $deliveryNote->received_by_phone }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- Order Info --}}
            <div class="mb-6 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">Order Details</flux:heading>

                <div class="mb-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">Order No</span>
                        <p class="font-medium text-zinc-900 dark:text-white">
                            <a href="{{ route('orders.show', $deliveryNote->order) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                                {{ $deliveryNote->order?->order_no }}
                            </a>
                        </p>
                    </div>
                    <div>
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">Customer</span>
                        <p class="font-medium text-zinc-900 dark:text-white">
                            {{ $deliveryNote->order?->customer?->name ?? 'N/A' }}
                        </p>
                        @if ($deliveryNote->order?->customer?->phone)
                            <p class="text-sm text-zinc-500">{{ $deliveryNote->order->customer->phone }}</p>
                        @endif
                    </div>
                </div>

                {{-- Order Lines --}}
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-2 text-left font-medium text-zinc-700 dark:text-zinc-300">Item</th>
                            <th class="pb-2 text-right font-medium text-zinc-700 dark:text-zinc-300">Qty</th>
                            <th class="pb-2 text-right font-medium text-zinc-700 dark:text-zinc-300">Unit Price</th>
                            <th class="pb-2 text-right font-medium text-zinc-700 dark:text-zinc-300">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($deliveryNote->order?->lines ?? [] as $line)
                            <tr>
                                <td class="py-2 text-zinc-900 dark:text-white">{{ $line->item_name }}</td>
                                <td class="py-2 text-right text-zinc-600 dark:text-zinc-400">{{ number_format($line->qty, 0) }}</td>
                                <td class="py-2 text-right font-mono text-zinc-600 dark:text-zinc-400">{{ number_format($line->unit_price, 0) }}</td>
                                <td class="py-2 text-right font-mono text-zinc-900 dark:text-white">{{ number_format($line->line_total, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td colspan="3" class="pt-3 text-right font-medium text-zinc-900 dark:text-white">Total</td>
                            <td class="pt-3 text-right font-mono text-lg font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ number_format($deliveryNote->order?->total ?? 0, 0) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Notes --}}
            @if ($deliveryNote->note)
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-2">Notes</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 whitespace-pre-wrap">{{ $deliveryNote->note }}</p>
                </div>
            @endif

            {{-- Back Button --}}
            <div class="mt-6">
                <flux:button variant="ghost" :href="route('orders.show', $deliveryNote->order)" wire:navigate>
                    <x-icon name="arrow_back" class="mr-1 size-4" />
                    Back to Order
                </flux:button>
            </div>
        </flux:card>
    </flux:main>
</div>

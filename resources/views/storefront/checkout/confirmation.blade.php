@extends('storefront.layouts.app')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <section class="rounded-2xl border border-green-200 bg-green-50 p-6 dark:border-green-800 dark:bg-green-900/20">
            <h1 class="text-2xl font-semibold text-green-800 dark:text-green-300">Order Submitted</h1>
            <p class="mt-2 text-sm text-green-700 dark:text-green-400">
                Your order <span class="font-semibold">{{ $order->order_no }}</span> has been placed successfully.
            </p>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <article class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">Order Details</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500">Order Number</dt><dd>{{ $order->order_no }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Payment Status</dt><dd>{{ $order->payment_status->label() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Fulfillment</dt><dd>{{ $order->fulfillment_status?->label() ?: 'Pending' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Total</dt><dd class="font-semibold">{{ money_currency($order->grand_total, $currency) }}</dd></div>
                </dl>
            </article>

            <article class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">Shipping</h2>
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ data_get($order->shipping_address, 'address_line1') }}
                    {{ data_get($order->shipping_address, 'address_line2') }}
                    {{ data_get($order->shipping_address, 'city') }}
                    {{ data_get($order->shipping_address, 'state') }}
                    {{ data_get($order->shipping_address, 'postal_code') }}
                    {{ data_get($order->shipping_address, 'country') }}
                </p>
                @if ($order->currentShipment)
                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                        Shipment status: <span class="font-medium">{{ ucfirst($order->currentShipment->status) }}</span>
                    </p>
                @endif
            </article>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">Items</h2>
            <div class="mt-4 space-y-3">
                @foreach ($order->lines as $line)
                    <div class="flex items-center justify-between text-sm">
                        <div>
                            <p class="font-medium">{{ $line->item_name }}</p>
                            <p class="text-zinc-500">Qty {{ (float) $line->qty }}</p>
                        </div>
                        <p class="font-semibold">{{ money_currency($line->line_total, $currency) }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            @auth
                <a href="{{ route('storefront.account.orders.show', $order) }}" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900">Track Order</a>
            @endauth
            <a href="{{ route('storefront.catalog.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">Continue Shopping</a>
        </div>
    </div>
@endsection

@php
    $businessName = $settings->business_name ?? config('app.name', 'TailorPro');
    $address = $settings->address ?: $settings->storefront_address;
    $phone = $settings->phone ?: $settings->storefront_contact_phone;
@endphp

<div class="pos-receipt-slip">
    <div class="pos-receipt-edge pos-receipt-edge-top" aria-hidden="true"></div>

    <div class="pos-receipt-body">
        <div class="text-center">
            <div class="text-xl font-semibold uppercase tracking-wide text-zinc-950">{{ __('Cash Receipt') }}</div>
            <div class="mx-auto mt-1 w-12 border-t border-dashed border-zinc-500"></div>
            <div class="mt-3 text-lg font-semibold text-zinc-950">{{ $businessName }}</div>
            @if ($address)
                <div class="text-sm leading-tight text-zinc-700">{{ $address }}</div>
            @endif
            @if ($phone)
                <div class="text-sm leading-tight text-zinc-700">{{ $phone }}</div>
            @endif
            <div class="mx-auto mt-2 w-12 border-t border-dashed border-zinc-500"></div>
        </div>

        <div class="mt-5 space-y-1 text-sm text-zinc-950">
            <div class="grid grid-cols-[5.5rem_minmax(0,1fr)] gap-3">
                <span>{{ __('Date') }}:</span>
                <span>{{ $sale->sold_at?->format('d/m/Y H:i') }}</span>
            </div>
            <div class="grid grid-cols-[5.5rem_minmax(0,1fr)] gap-3">
                <span>{{ __('Cashier') }}:</span>
                <span>{{ $sale->user?->name ?? '-' }}</span>
            </div>
            <div class="grid grid-cols-[5.5rem_minmax(0,1fr)] gap-3">
                <span>{{ __('Receipt') }}:</span>
                <span class="font-mono text-xs">{{ $sale->sale_number }}</span>
            </div>
            <div class="grid grid-cols-[5.5rem_minmax(0,1fr)] gap-3">
                <span>{{ __('Customer') }}:</span>
                <span>{{ $sale->customer?->name ?? __('Walk-in Customer') }}</span>
            </div>
        </div>

        <div class="my-4 border-t-2 border-dashed border-zinc-800"></div>

        <table class="w-full text-sm text-zinc-950">
            <thead>
                <tr class="border-b border-zinc-400 text-left text-xs font-bold uppercase tracking-wide">
                    <th class="pb-2">{{ __('Item') }}</th>
                    <th class="pb-2 text-center">{{ __('Qty') }}</th>
                    <th class="pb-2 text-right">{{ __('Price') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td class="py-1.5 pr-2 align-top">
                            <div class="font-medium leading-tight">{{ $item->item_name }}</div>
                            @if ($item->sku)
                                <div class="text-[11px] text-zinc-500">{{ $item->sku }}</div>
                            @endif
                        </td>
                        <td class="py-1.5 text-center align-top">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                        <td class="py-1.5 text-right align-top font-mono">{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="my-4 border-t-2 border-dashed border-zinc-800"></div>

        <div class="space-y-1 text-sm text-zinc-950">
            <div class="flex justify-between">
                <span>{{ __('Subtotal') }}</span>
                <span class="font-mono">{{ number_format((float) $sale->subtotal, 2) }}</span>
            </div>
            @if ((float) $sale->discount_amount > 0)
                <div class="flex justify-between">
                    <span>{{ __('Discount') }}</span>
                    <span class="font-mono">-{{ number_format((float) $sale->discount_amount, 2) }}</span>
                </div>
            @endif
            <div class="flex justify-between">
                <span>{{ __('Tax') }}</span>
                <span class="font-mono">{{ number_format((float) $sale->tax_amount, 2) }}</span>
            </div>
            <div class="flex items-end justify-between pt-2">
                <span class="text-2xl font-black uppercase tracking-wide">{{ __('Total') }}</span>
                <span class="font-mono text-xl font-black">{{ number_format((float) $sale->total_amount, 2) }}</span>
            </div>
        </div>

        <div class="my-4 border-t-2 border-dashed border-zinc-800"></div>

        <div class="space-y-1 text-sm text-zinc-950">
            <div class="flex justify-between">
                <span>{{ \Illuminate\Support\Str::of((string) $sale->payment_method)->replace('_', ' ')->title() }}</span>
                <span class="font-mono">{{ number_format((float) $sale->amount_paid, 2) }}</span>
            </div>
            @if ($sale->payment_reference)
                <div class="break-all text-xs">{{ $sale->payment_reference }}</div>
            @endif
            <div class="flex justify-between">
                <span>{{ __('Change') }}</span>
                <span class="font-mono">{{ number_format((float) $sale->change_amount, 2) }}</span>
            </div>
        </div>

        <div class="my-5 border-t border-zinc-400"></div>

        <div class="text-center">
            @if ($receiptQrCodeSvg)
                <div class="mx-auto flex size-32 items-center justify-center overflow-hidden text-zinc-950">
                    {!! $receiptQrCodeSvg !!}
                </div>
            @endif
            @if ($receiptUrl)
                <div class="mx-auto mt-2 max-w-52 break-all text-[10px] leading-tight text-zinc-500">{{ $receiptUrl }}</div>
            @endif
            <div class="mt-5 text-lg font-black uppercase tracking-wide text-zinc-950">{{ __('Thank You') }}</div>
        </div>
    </div>

    <div class="pos-receipt-edge pos-receipt-edge-bottom" aria-hidden="true"></div>
</div>

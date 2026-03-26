@extends('storefront.layouts.app')

@section('title', __('Order Details'))

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('storefront.account.dashboard') }}">{{ __('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('storefront.account.orders.index') }}">{{ __('My Orders') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $order->order_no }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    @php
        $balanceDue = (float) $order->balance_due;
        $statusLabel = $order->fulfillment_status?->label() ?? $order->status->label();
    @endphp

    <section class="middle">
        <div class="container">
            <div class="row align-items-start justify-content-between">
                <div class="col-12 col-md-12 col-lg-4 col-xl-4 text-center miliods mb-4 mb-lg-0">
                    <x-storefront.account-sidebar :customer="$customer" :settings="$settings" active="orders" />
                </div>

                <div class="col-12 col-md-12 col-lg-8 col-xl-8">
                    <div class="ord_list_wrap border mb-4">
                        <div class="ord_list_head gray d-flex align-items-center justify-content-between px-3 py-3">
                            <div class="olh_flex text-start">
                                <p class="m-0 p-0"><span class="text-muted">{{ __('Order Number') }}</span></p>
                                <h6 class="mb-0 ft-medium">{{ $order->order_no }}</h6>
                            </div>
                            <div class="olh_flex">
                                <a href="{{ route('storefront.account.orders.index') }}" class="btn btn-sm btn-outline-dark">{{ __('Back') }}</a>
                            </div>
                        </div>

                        <div class="ord_list_body text-left px-3 py-4">
                            <div class="row g-3">
                                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6">
                                    <div class="gray rounded px-3 py-3">
                                        <p class="mb-1 text-muted small">{{ __('Total') }}</p>
                                        <h6 class="mb-0 ft-bold">{{ money_currency($order->payableTotal(), $currency) }}</h6>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6">
                                    <div class="gray rounded px-3 py-3">
                                        <p class="mb-1 text-muted small">{{ __('Paid') }}</p>
                                        <h6 class="mb-0 ft-bold">{{ money_currency($order->paid_amount, $currency) }}</h6>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                                    <div class="gray rounded px-3 py-3">
                                        <p class="mb-1 text-muted small">{{ __('Balance Due') }}</p>
                                        <h6 class="mb-0 ft-bold">{{ money_currency($balanceDue, $currency) }}</h6>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap align-items-center justify-content-between mt-3 gap-2">
                                <p class="mb-0 small text-muted">{{ __('Payment Status') }}: <span class="ft-medium text-dark">{{ $order->payment_status->label() }}</span></p>
                                <p class="mb-0 small text-muted">{{ __('Fulfillment Status') }}: <span class="ft-medium text-dark">{{ $statusLabel }}</span></p>
                            </div>

                            @if ($balanceDue > 0)
                                <form method="POST" action="{{ route('storefront.account.orders.retry-payment', $order) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn btn-dark">{{ __('Retry Payment') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="ord_list_wrap border mb-4">
                        <div class="ord_list_head gray px-3 py-3">
                            <h6 class="mb-0 ft-medium">{{ __('Order Timeline') }}</h6>
                        </div>
                        <div class="ord_list_body text-left">
                            @forelse ($order->statusHistory->where('is_customer_visible', true) as $entry)
                                <div @class([
                                    'px-3 py-3',
                                    'br-bottom' => ! $loop->last,
                                ])>
                                    <h6 class="mb-1 fs-sm ft-medium">{{ $entry->title ?: str($entry->status)->replace('_', ' ')->title() }}</h6>
                                    @if ($entry->note)
                                        <p class="mb-1 text-muted small">{{ $entry->note }}</p>
                                    @endif
                                    <p class="mb-0 text-muted small">{{ $entry->created_at?->format('M d, Y H:i') }}</p>
                                </div>
                            @empty
                                <div class="px-3 py-3">
                                    <p class="mb-0 text-muted">{{ __('Order placed and awaiting updates.') }}</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="ord_list_wrap border mb-4">
                        <div class="ord_list_head gray px-3 py-3">
                            <h6 class="mb-0 ft-medium">{{ __('Shipment') }}</h6>
                        </div>
                        <div class="ord_list_body text-left px-3 py-3">
                            @if ($order->currentShipment)
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <p class="mb-1 text-muted small">{{ __('Status') }}</p>
                                        <p class="mb-0 ft-medium">{{ ucfirst($order->currentShipment->status) }}</p>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <p class="mb-1 text-muted small">{{ __('Carrier') }}</p>
                                        <p class="mb-0 ft-medium">{{ $order->currentShipment->carrier_name ?: '-' }}</p>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <p class="mb-1 text-muted small">{{ __('Tracking Number') }}</p>
                                        <p class="mb-0 ft-medium">{{ $order->currentShipment->tracking_number ?: '-' }}</p>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <p class="mb-1 text-muted small">{{ __('Shipped At') }}</p>
                                        <p class="mb-0 ft-medium">{{ $order->currentShipment->shipped_at?->format('M d, Y H:i') ?: '-' }}</p>
                                    </div>
                                </div>

                                @if ($order->currentShipment->tracking_url)
                                    <a href="{{ $order->currentShipment->tracking_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark btn-sm mt-3">{{ __('Track Shipment') }}</a>
                                @endif
                            @else
                                <p class="mb-0 text-muted">{{ __('Shipment details are not available yet.') }}</p>
                            @endif
                        </div>
                    </div>

                     <div class="ord_list_wrap border">
                         <div class="ord_list_head gray px-3 py-3">
                             <h6 class="mb-0 ft-medium">{{ __('Items') }}</h6>
                         </div>
                         <div class="ord_list_body text-left">
                             @forelse ($order->lines as $line)
                                 @php
                                     $formattedQty = rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.');
                                     if ($formattedQty === '') {
                                         $formattedQty = '0';
                                     }
                                 @endphp
                                 <div @class([
                                     'd-flex align-items-center justify-content-between px-3 py-3',
                                     'br-bottom' => ! $loop->last,
                                 ])>
                                     <div>
                                         <p class="mb-1 ft-medium">{{ $line->item_name }}</p>
                                         <p class="mb-0 small text-muted">{{ __('Qty') }}: {{ $formattedQty }}</p>
                                     </div>
                                     <p class="mb-0 ft-bold">{{ money_currency($line->line_total, $currency) }}</p>
                                 </div>
                             @empty
                                 <div class="px-3 py-3">
                                     <p class="mb-0 text-muted">{{ __('No order items found.') }}</p>
                                 </div>
                             @endforelse
                         </div>
                     </div>
                 </div>
             </div>
         </div>
     </section>
@endsection

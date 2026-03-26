@extends('storefront.layouts.app')

@section('title', __('My Orders'))

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('storefront.account.dashboard') }}">{{ __('Dashboard') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('My Orders') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    @php
        $statusClasses = [
            'pending' => 'text-warning bg-light-warning rounded px-3 py-1',
            'awaiting_payment' => 'text-warning bg-light-warning rounded px-3 py-1',
            'processing' => 'text-warning bg-light-warning rounded px-3 py-1',
            'in_progress' => 'text-warning bg-light-warning rounded px-3 py-1',
            'ready' => 'text-warning bg-light-warning rounded px-3 py-1',
            'ready_for_dispatch' => 'text-warning bg-light-warning rounded px-3 py-1',
            'shipped' => 'text-warning bg-light-warning rounded px-3 py-1',
            'delivered' => 'text-success bg-light-success rounded px-3 py-1',
            'completed' => 'text-success bg-light-success rounded px-3 py-1',
            'paid' => 'text-success bg-light-success rounded px-3 py-1',
            'cancelled' => 'text-danger bg-light-danger rounded px-3 py-1',
            'failed_payment' => 'text-danger bg-light-danger rounded px-3 py-1',
            'refunded' => 'text-danger bg-light-danger rounded px-3 py-1',
            'unpaid' => 'text-danger bg-light-danger rounded px-3 py-1',
            'partial' => 'text-warning bg-light-warning rounded px-3 py-1',
        ];
    @endphp

    <section class="middle">
        <div class="container">
            <div class="row align-items-start justify-content-between">
                <div class="col-12 col-md-12 col-lg-4 col-xl-4 text-center miliods mb-4 mb-lg-0">
                    <x-storefront.account-sidebar :customer="$customer" :settings="$settings" active="orders" />
                </div>

                <div class="col-12 col-md-12 col-lg-8 col-xl-8">
                    @forelse ($orders as $order)
                        @php
                            $statusKey = strtolower((string) ($order->fulfillment_status?->value ?? $order->status->value));
                            $paymentStatusKey = strtolower((string) $order->payment_status->value);
                            $statusClass = $statusClasses[$statusKey] ?? 'text-muted bg-light rounded px-3 py-1';
                            $paymentStatusClass = $statusClasses[$paymentStatusKey] ?? 'text-muted bg-light rounded px-3 py-1';
                            $statusLabel = $order->fulfillment_status?->label() ?? $order->status->label();
                            $timelineDate = $order->currentShipment?->delivered_at
                                ?? $order->currentShipment?->shipped_at
                                ?? $order->placed_at
                                ?? $order->created_at;
                            $timelineLabel = $order->currentShipment?->delivered_at
                                ? __('Delivered on')
                                : ($order->currentShipment?->shipped_at ? __('Shipped on') : __('Placed on'));
                        @endphp

                        <div class="ord_list_wrap border mb-4">
                            <div class="ord_list_head gray d-flex align-items-center justify-content-between px-3 py-3">
                                <div class="olh_flex">
                                    <p class="m-0 p-0"><span class="text-muted">{{ __('Order Number') }}</span></p>
                                    <h6 class="mb-0 ft-medium">{{ $order->order_no }}</h6>
                                </div>
                                <div class="olh_flex">
                                    <a href="{{ route('storefront.account.orders.show', $order) }}" class="btn btn-sm btn-dark">{{ __('View Details') }}</a>
                                </div>
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
                                        'row align-items-center justify-content-center m-0 py-4',
                                        'br-bottom' => ! $loop->last,
                                    ])>
                                        <div class="col-xl-5 col-lg-5 col-md-5 col-12">
                                            <div class="cart_single d-flex align-items-start mfliud-bot">
                                                <div class="cart_selected_single_thumb">
                                                    <a href="javascript:void(0);">
                                                        <img src="{{ asset('frontend/assets/img/product/1.jpg') }}" width="75" class="img-fluid rounded" alt="{{ $line->item_name }}">
                                                    </a>
                                                </div>
                                                <div class="cart_single_caption text-start ps-3">
                                                    <p class="mb-0"><span class="text-muted small">{{ __('Item') }}</span></p>
                                                    <h4 class="product_title fs-sm ft-medium mb-1 lh-1">{{ $line->item_name }}</h4>
                                                    <p class="mb-2"><span class="text-dark medium">{{ __('Qty') }}: {{ $formattedQty }}</span></p>
                                                    <h4 class="fs-sm ft-bold mb-0 lh-1">{{ money_currency($line->line_total, $currency) }}</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-lg-3 col-md-3 col-6 text-start">
                                            <p class="mb-1 p-0"><span class="text-muted">{{ __('Status') }}</span></p>
                                            <div class="delv_status">
                                                <span class="ft-medium small {{ $statusClass }}">{{ $statusLabel }}</span>
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-lg-4 col-md-4 col-6 text-start">
                                            <p class="mb-1 p-0"><span class="text-muted">{{ $timelineLabel }}:</span></p>
                                            <h6 class="mb-0 ft-medium fs-sm">{{ $timelineDate?->format('d F Y') ?? '-' }}</h6>
                                        </div>
                                    </div>
                                @empty
                                    <div class="px-3 py-4">
                                        <p class="mb-0 text-muted">{{ __('No order items found.') }}</p>
                                    </div>
                                @endforelse
                            </div>

                            <div class="ord_list_footer d-flex align-items-center justify-content-between br-top px-3 text-start">
                                <div class="col-xl-4 col-lg-4 col-md-5 olf_flex text-left px-0 py-2 br-right">
                                    <a href="{{ route('storefront.account.orders.show', $order) }}" class="ft-medium fs-sm">
                                        <i class="fa-light fa-arrow-right me-2"></i>{{ __('Track Order') }}
                                    </a>
                                </div>
                                <div class="col-xl-8 col-lg-8 col-md-7 pe-0 ps-2 py-2 olf_flex d-flex align-items-center justify-content-between">
                                    <div class="olf_flex_inner">
                                        <span class="ft-medium small {{ $paymentStatusClass }}">{{ __('Payment: :status', ['status' => $order->payment_status->label()]) }}</span>
                                    </div>
                                    <div class="olf_inner_right">
                                        <h5 class="mb-0 fs-sm ft-bold">{{ __('Total: :total', ['total' => money_currency($order->payableTotal(), $currency)]) }}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="ord_list_wrap border">
                            <div class="ord_list_body text-left px-4 py-4">
                                <p class="mb-0 text-muted">{{ __('No storefront orders found.') }}</p>
                            </div>
                        </div>
                    @endforelse

                    @if ($orders->hasPages())
                        <div class="d-flex justify-content-center mt-3">
                            {{ $orders->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection

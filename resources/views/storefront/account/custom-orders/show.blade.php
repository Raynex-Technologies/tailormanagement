@extends('storefront.layouts.app')

@section('title', __('Custom Order Details'))

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('storefront.account.dashboard') }}">{{ __('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('storefront.account.custom-orders.index') }}">{{ __('Custom Orders') }}</a></li>
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
    @endphp

    <section class="middle">
        <div class="container">
            <div class="row align-items-start justify-content-between">
                <div class="col-12 col-md-12 col-lg-4 col-xl-4 text-center miliods mb-4 mb-lg-0">
                    <x-storefront.account-sidebar :customer="$customer" :settings="$settings" active="custom-orders" />
                </div>

                <div class="col-12 col-md-12 col-lg-8 col-xl-8">
                    <div class="ord_list_wrap border mb-4">
                        <div class="ord_list_head gray d-flex align-items-center justify-content-between px-3 py-3">
                            <div class="olh_flex text-start">
                                <p class="m-0 p-0"><span class="text-muted">{{ __('Order Number') }}</span></p>
                                <h6 class="mb-0 ft-medium">{{ $order->order_no }}</h6>
                            </div>
                            <div class="olh_flex">
                                <a href="{{ route('storefront.account.custom-orders.index') }}" class="btn btn-sm btn-outline-dark">{{ __('Back') }}</a>
                            </div>
                        </div>

                        <div class="ord_list_body text-left px-3 py-4">
                            <div class="row g-3">
                                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6">
                                    <div class="gray rounded px-3 py-3">
                                        <p class="mb-1 text-muted small">{{ __('Order Total') }}</p>
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
                                        <p class="mb-1 text-muted small">{{ __('Balance') }}</p>
                                        <h6 class="mb-0 ft-bold">{{ money_currency($balanceDue, $currency) }}</h6>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <p class="mb-0 small text-muted">{{ __('Status') }}: <span class="ft-medium text-dark">{{ $order->status->label() }}</span></p>
                            </div>

                            @if ($balanceDue > 0)
                                <form method="POST" action="{{ route('storefront.account.custom-orders.pay', $order) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn btn-dark">{{ __('Pay Outstanding Balance') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="ord_list_wrap border mb-4">
                        <div class="ord_list_head gray px-3 py-3">
                            <h6 class="mb-0 ft-medium">{{ __('Progress Timeline') }}</h6>
                        </div>
                        <div class="ord_list_body text-left">
                            @forelse ($order->customProgressUpdates->where('is_customer_visible', true) as $update)
                                <div @class([
                                    'px-3 py-3',
                                    'br-bottom' => ! $loop->last,
                                ])>
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                        <div>
                                            <h6 class="mb-1 fs-sm ft-medium">{{ $update->stage_label }}</h6>
                                            @if ($update->note)
                                                <p class="mb-1 text-muted small">{{ $update->note }}</p>
                                            @endif

                                            @if ($update->requested_payment_amount)
                                                @php
                                                    $requestedAmount = min((float) $update->requested_payment_amount, $balanceDue);
                                                @endphp
                                                <p class="mb-2 small text-warning">{{ __('Payment requested: :amount', ['amount' => money_currency($update->requested_payment_amount, $currency)]) }}</p>
                                                @if ($balanceDue > 0 && $requestedAmount > 0)
                                                    <form method="POST" action="{{ route('storefront.account.custom-orders.pay', $order) }}">
                                                        @csrf
                                                        <input type="hidden" name="amount" value="{{ $requestedAmount }}">
                                                        <button type="submit" class="btn btn-sm btn-dark">{{ __('Pay :amount', ['amount' => money_currency($requestedAmount, $currency)]) }}</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                        <p class="mb-0 small text-muted">{{ $update->created_at?->format('M d, Y H:i') }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="px-3 py-3">
                                    <p class="mb-0 text-muted">{{ __('No progress updates have been published yet.') }}</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="ord_list_wrap border">
                        <div class="ord_list_head gray px-3 py-3">
                            <h6 class="mb-0 ft-medium">{{ __('Payment History') }}</h6>
                        </div>
                        <div class="ord_list_body text-left">
                            @forelse ($order->payments as $payment)
                                <div @class([
                                    'd-flex align-items-center justify-content-between px-3 py-3',
                                    'br-bottom' => ! $loop->last,
                                ])>
                                    <div>
                                        <p class="mb-1 ft-medium">{{ money_currency($payment->amount, $currency) }}</p>
                                        <p class="mb-0 small text-muted">
                                            {{ $payment->paid_at?->format('M d, Y H:i') ?: '-' }}
                                            {{ __('via') }}
                                            {{ $payment->paymentMethod?->name ?: __('Payment') }}
                                        </p>
                                    </div>
                                    <p class="mb-0 small text-muted">{{ $payment->reference ?: '-' }}</p>
                                </div>
                            @empty
                                <div class="px-3 py-3">
                                    <p class="mb-0 text-muted">{{ __('No payments recorded yet.') }}</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

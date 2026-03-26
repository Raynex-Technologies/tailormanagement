@extends('storefront.layouts.app')

@section('title', __('Custom Orders'))

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('storefront.account.dashboard') }}">{{ __('Dashboard') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Custom Orders') }}</li>
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
            'new' => 'text-warning bg-light-warning rounded px-3 py-1',
            'in_progress' => 'text-warning bg-light-warning rounded px-3 py-1',
            'ready' => 'text-warning bg-light-warning rounded px-3 py-1',
            'delivered' => 'text-success bg-light-success rounded px-3 py-1',
            'completed' => 'text-success bg-light-success rounded px-3 py-1',
            'cancelled' => 'text-danger bg-light-danger rounded px-3 py-1',
        ];
    @endphp

    <section class="middle">
        <div class="container">
            <div class="row align-items-start justify-content-between">
                <div class="col-12 col-md-12 col-lg-4 col-xl-4 text-center miliods mb-4 mb-lg-0">
                    <x-storefront.account-sidebar :customer="$customer" :settings="$settings" active="custom-orders" />
                </div>

                <div class="col-12 col-md-12 col-lg-8 col-xl-8">
                    @forelse ($orders as $order)
                        @php
                            $statusKey = strtolower((string) $order->status->value);
                            $statusClass = $statusClasses[$statusKey] ?? 'text-muted bg-light rounded px-3 py-1';
                        @endphp

                        <div class="ord_list_wrap border mb-4">
                            <div class="ord_list_head gray d-flex align-items-center justify-content-between px-3 py-3">
                                <div class="olh_flex text-start">
                                    <p class="m-0 p-0"><span class="text-muted">{{ __('Order Number') }}</span></p>
                                    <h6 class="mb-0 ft-medium">{{ $order->order_no }}</h6>
                                </div>
                                <div class="olh_flex">
                                    <a href="{{ route('storefront.account.custom-orders.show', $order) }}" class="btn btn-sm btn-dark">{{ __('View Details') }}</a>
                                </div>
                            </div>

                            <div class="ord_list_body text-left px-3 py-4">
                                <div class="row g-3 align-items-center">
                                    <div class="col-12 col-md-4">
                                        <p class="mb-1 p-0"><span class="text-muted">{{ __('Status') }}</span></p>
                                        <span class="ft-medium small {{ $statusClass }}">{{ $order->status->label() }}</span>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <p class="mb-1 p-0"><span class="text-muted">{{ __('Total') }}</span></p>
                                        <h6 class="mb-0 ft-medium fs-sm">{{ money_currency($order->payableTotal(), $currency) }}</h6>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <p class="mb-1 p-0"><span class="text-muted">{{ __('Balance') }}</span></p>
                                        <h6 class="mb-0 ft-medium fs-sm">{{ money_currency($order->balance_due, $currency) }}</h6>
                                    </div>
                                </div>
                            </div>

                            <div class="ord_list_footer d-flex align-items-center justify-content-between br-top px-3 text-start">
                                <div class="col-xl-4 col-lg-4 col-md-5 olf_flex text-left px-0 py-2 br-right">
                                    <a href="{{ route('storefront.account.custom-orders.show', $order) }}" class="ft-medium fs-sm">
                                        <i class="fa-light fa-arrow-right me-2"></i>{{ __('Track Progress') }}
                                    </a>
                                </div>
                                <div class="col-xl-8 col-lg-8 col-md-7 pe-0 ps-2 py-2 olf_flex d-flex align-items-center justify-content-end">
                                    <div class="olf_inner_right">
                                        <h5 class="mb-0 fs-sm ft-bold">{{ __('Placed: :date', ['date' => ($order->placed_at ?: $order->created_at)?->format('M d, Y')]) }}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="ord_list_wrap border">
                            <div class="ord_list_body text-left px-4 py-4">
                                <p class="mb-0 text-muted">{{ __('No custom orders found.') }}</p>
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

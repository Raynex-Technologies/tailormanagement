@extends('storefront.layouts.app')

@section('title', __('My Dashboard'))

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Dashboard') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="middle">
        <div class="container">
            <div class="row align-items-start justify-content-between">
                <div class="col-12 col-md-12 col-lg-4 col-xl-4 text-center miliods mb-4 mb-lg-0">
                    <x-storefront.account-sidebar :customer="$customer" :settings="$settings" active="dashboard" />
                </div>

                <div class="col-12 col-md-12 col-lg-8 col-xl-8">
                    <div class="row g-3">
                        <div class="col-xl-4 col-lg-4 col-md-6 col-sm-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1">{{ __('Storefront Orders') }}</p>
                                    <h3 class="ft-bold mb-0">{{ $storefrontOrderCount }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-6 col-sm-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1">{{ __('Custom Orders') }}</p>
                                    <h3 class="ft-bold mb-0">{{ $customOrderCount }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-6 col-sm-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1">{{ __('Saved Addresses') }}</p>
                                    <h3 class="ft-bold mb-0">{{ auth()->user()->customerAddresses()->count() }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <h4 class="mb-0 fs-6 ft-bold">{{ __('Recent Activity') }}</h4>
                            <div class="d-flex align-items-center gap-3">
                                <a href="{{ route('storefront.account.orders.index') }}" class="small theme-cl">{{ __('Storefront Orders') }}</a>
                                @if ($settings->custom_order_portal_enabled)
                                    <a href="{{ route('storefront.account.custom-orders.index') }}" class="small theme-cl">{{ __('Custom Orders') }}</a>
                                @endif
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @forelse ($orders as $order)
                                <a
                                    href="{{ $order->order_type === 'storefront' ? route('storefront.account.orders.show', $order) : route('storefront.account.custom-orders.show', $order) }}"
                                    class="d-flex align-items-center justify-content-between px-4 py-3 border-top text-dark"
                                >
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light" style="width: 38px; height: 38px;">
                                            <i class="fa-light {{ $order->order_type === 'storefront' ? 'fa-bag-shopping' : 'fa-scissors' }}"></i>
                                        </span>
                                        <div>
                                            <p class="mb-0 ft-medium">{{ $order->order_no }}</p>
                                            <span class="text-muted small">{{ ucfirst($order->order_type) }} • {{ $order->created_at->format('M d, Y') }}</span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="small text-muted d-block">{{ $order->status->label() }}</span>
                                        <span class="ft-bold">{{ money_currency($order->payableTotal(), $currency) }}</span>
                                    </div>
                                </a>
                            @empty
                                <div class="px-4 py-4 text-muted">{{ __('No orders yet.') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

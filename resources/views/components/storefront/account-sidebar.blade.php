@props([
    'customer',
    'active' => 'dashboard',
    'settings' => null,
])

@php
    $settings = $settings ?? \App\Models\BusinessSetting::instance();
@endphp

<div class="d-block border rounded">
    <div class="dashboard_author px-2 py-5 text-center">
        <div class="dash_auth_thumb circle p-1 border d-inline-flex mx-auto mb-2 align-items-center justify-content-center">
            <span class="d-inline-flex align-items-center justify-content-center bg-dark text-white circle" style="width: 100px; height: 100px; font-size: 30px;">
                {{ strtoupper(substr($customer->name, 0, 1)) }}
            </span>
        </div>
        <div class="dash_caption">
            <h4 class="fs-md ft-medium mb-0 lh-1">{{ $customer->name }}</h4>
            @if ($customer->email)
                <span class="text-muted smalls">{{ $customer->email }}</span>
            @endif
        </div>
    </div>

    <div class="dashboard_author">
        <h4 class="px-3 py-2 mb-0 lh-2 gray fs-sm ft-medium text-muted text-uppercase text-left">{{ __('Dashboard Navigation') }}</h4>
        <ul class="dahs_navbar">
            <li>
                <a href="{{ route('storefront.account.dashboard') }}" @class(['active' => $active === 'dashboard'])>
                    <i class="fa-light fa-grid-2 me-2"></i>{{ __('Dashboard') }}
                </a>
            </li>
            <li>
                <a href="{{ route('storefront.account.orders.index') }}" @class(['active' => $active === 'orders'])>
                    <i class="fa-light fa-bag-shopping me-2"></i>{{ __('My Orders') }}
                </a>
            </li>
            @if ($settings->custom_order_portal_enabled)
                <li>
                    <a href="{{ route('storefront.account.custom-orders.index') }}" @class(['active' => $active === 'custom-orders'])>
                        <i class="fa-light fa-scissors me-2"></i>{{ __('Custom Orders') }}
                    </a>
                </li>
            @endif
            <li>
                <a href="{{ route('storefront.account.addresses.index') }}" @class(['active' => $active === 'addresses'])>
                    <i class="fa-light fa-location-dot me-2"></i>{{ __('Addresses') }}
                </a>
            </li>
            <li>
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="w-100 border-0 bg-transparent text-start">
                        <span class="d-block px-3 py-3">
                            <i class="fa-light fa-right-from-bracket me-2"></i>{{ __('Log Out') }}
                        </span>
                    </button>
                </form>
            </li>
        </ul>
    </div>
</div>

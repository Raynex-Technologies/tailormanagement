@props([
    'settings' => null,
    'footerMenuLinks' => collect(),
])

@php
    $settings = $settings ?? \App\Models\BusinessSetting::instance();
    $footerMenuLinks = $footerMenuLinks instanceof \Illuminate\Support\Collection ? $footerMenuLinks : collect($footerMenuLinks);
    $businessName = $settings->business_name ?: config('app.name');
@endphp

<footer class="dark-footer skin-dark-footer style-2">
    <div class="footer-middle">
        <div class="container">
            <div class="row">
                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12">
                    <div class="footer_widget">
                        <h4 class="widget_title">{{ $businessName }}</h4>
                        <div class="address mt-3">
                            {{ $settings->storefront_address ?: $settings->address ?: __('Store address not set') }}
                        </div>
                        <div class="address mt-3">
                            {{ $settings->storefront_contact_phone ?: $settings->phone ?: '-' }}<br>
                            {{ $settings->storefront_contact_email ?: $settings->email ?: '-' }}
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12">
                    <div class="footer_widget">
                        <h4 class="widget_title">{{ __('Pages') }}</h4>
                        <ul class="footer-menu">
                            @forelse ($footerMenuLinks as $link)
                                <li>
                                    <a href="{{ $link['url'] }}" target="{{ $link['target'] }}" @if($link['target'] === '_blank') rel="noopener noreferrer" @endif>
                                        {{ $link['label'] }}
                                    </a>
                                </li>
                            @empty
                                <li><a href="{{ route('storefront.catalog.index') }}">{{ __('Shop') }}</a></li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12">
                    <div class="footer_widget">
                        <h4 class="widget_title">{{ __('Shop') }}</h4>
                        <ul class="footer-menu">
                            <li><a href="{{ route('storefront.catalog.index') }}">{{ __('All Products') }}</a></li>
                            <li><a href="{{ route('storefront.catalog.index', ['featured' => 1]) }}">{{ __('Featured Products') }}</a></li>
                            <li><a href="{{ route('storefront.catalog.index', ['q' => 'combo']) }}">{{ __('Combo Deals') }}</a></li>
                            <li><a href="{{ route('storefront.cart.index') }}">{{ __('My Cart') }}</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12">
                    <div class="footer_widget">
                        <h4 class="widget_title">{{ __('Customer Portal') }}</h4>
                        <ul class="footer-menu">
                            <li><a href="{{ route('storefront.account.dashboard') }}">{{ __('Dashboard') }}</a></li>
                            <li><a href="{{ route('storefront.account.orders.index') }}">{{ __('My Orders') }}</a></li>
                            @if ($settings->custom_order_portal_enabled)
                                <li><a href="{{ route('storefront.account.custom-orders.index') }}">{{ __('Custom Orders') }}</a></li>
                            @endif
                            <li><a href="{{ route('storefront.account.addresses.index') }}">{{ __('Addresses') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12 col-md-12 text-center">
                    <p class="mb-0">&copy; {{ now()->year }} {{ $businessName }}. {{ __('All rights reserved.') }}</p>
                </div>
            </div>
        </div>
    </div>
</footer>

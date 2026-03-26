@props([
    'settings' => null,
    'headerMenuLinks' => collect(),
    'cartItemsCount' => 0,
])

@php
    $settings = $settings ?? \App\Models\BusinessSetting::instance();
    $headerMenuLinks = $headerMenuLinks instanceof \Illuminate\Support\Collection ? $headerMenuLinks : collect($headerMenuLinks);
    $businessName = $settings->business_name ?: config('app.name');
@endphp

<div class="header header-light dark-text">
    <div class="container">
        <nav id="navigation" class="navigation navigation-landscape">
            <div class="nav-header">
                <a class="nav-brand" href="{{ route('storefront.home') }}">
                    @if ($settings->storefront_logo_url)
                        <img src="{{ $settings->storefront_logo_url }}" class="logo" alt="{{ $businessName }}" />
                    @else
                        <span class="ft-bold fs-5 text-dark">{{ $businessName }}</span>
                    @endif
                </a>
                <div class="nav-toggle"></div>
                <div class="mobile_nav">
                    <ul>
                        <li>
                            <a href="{{ route('storefront.catalog.index') }}">
                                <i class="fa-light fa-magnifying-glass"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ auth()->check() ? route('storefront.account.dashboard') : route('login') }}">
                                <i class="fa-light fa-user"></i>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('storefront.cart.index') }}">
                                <i class="fa-light fa-bag-shopping"></i>
                                <span class="dn-counter">{{ $cartItemsCount }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="nav-menus-wrapper" style="transition-property: none;">
                <ul class="nav-menu">
                    <li><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                    <li><a href="{{ route('storefront.catalog.index') }}">{{ __('Shop') }}</a></li>

                    @foreach ($headerMenuLinks as $link)
                        <li>
                            <a href="{{ $link['url'] }}" target="{{ $link['target'] }}" @if($link['target'] === '_blank') rel="noopener noreferrer" @endif>
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach

                    
                </ul>

                <ul class="nav-menu nav-menu-social align-to-right">
                    <li>
                        <a href="{{ route('storefront.cart.index') }}">
                            <i class="fa-light fa-bag-shopping"></i>
                            <span class="dn-counter theme-bg">{{ $cartItemsCount }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ auth()->check() ? route('storefront.account.dashboard') : route('login') }}">
                            <i class="fa-light fa-user"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</div>
<div class="clearfix"></div>

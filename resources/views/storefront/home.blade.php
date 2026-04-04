@extends('storefront.layouts.app')

@section('title', $settings->storefront_seo_title ?: ($settings->business_name ?: config('app.name')))

@section('content')
    @php
        $currency = $settings->storefront_currency ?: config('storefront.currency', 'USD');
        $heroTitle = data_get($sections, 'hero.title') ?: __('TailorPro Online Store');
        $heroContent = data_get($sections, 'hero.content') ?: __('Shop ready-made products, discover combo offers, and track custom tailoring orders in one place.');
    @endphp

    <section class="p-0">
        <div class="container-fluid p-0">
            <div class="row g-0">
                @forelse ($featuredCategories->take(3) as $category)
                    @php
                        $fallback = asset('frontend/assets/img/a-'.(($loop->index % 3) + 1).'.png');
                        $categoryImage = $category->image_url ?: $fallback;
                    @endphp
                    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                        <a href="{{ route('storefront.catalog.index', ['category' => $category->slug]) }}" class="card card-overflow card-scale no-radius mb-0">
                            <div class="bg-image" style="background:url('{{ $categoryImage }}') no-repeat;" data-overlay="2"></div>
                            <div class="ct_body">
                                <div class="ct_body_caption">
                                    <h1 class="mb-0 ft-bold text-light">{{ $category->name }}</h1>
                                </div>
                                <div class="ct_footer">
                                    <span class="btn btn-white stretched-links">
                                        {{ __('Shop Now') }}
                                        <i class="fa-light fa-arrow-right ms-1"></i>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card no-radius mb-0 border-0">
                            <div class="card-body text-center py-5">
                                <x-storefront.section-title :eyebrow="__('Welcome')" :title="$heroTitle" />
                                <p class="mb-0 text-muted">{{ $heroContent }}</p>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="middle pt-4 pb-0">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-8 col-lg-10 col-md-12 text-center">
                    <x-storefront.section-title :eyebrow="__('TailorPro Storefront')" :title="$heroTitle" />
                    <p class="text-muted">{{ $heroContent }}</p>
                    <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                        <a href="{{ route('storefront.catalog.index') }}" class="btn btn-dark rounded-pill px-4">
                            <i class="fa-light fa-bag-shopping me-1"></i>{{ __('Browse Products') }}
                        </a>
                        @if ($settings->custom_order_portal_enabled)
                            <a href="{{ route('storefront.account.custom-orders.index') }}" class="btn borders rounded-pill px-4">
                                <i class="fa-light fa-scissors me-1"></i>{{ __('Track Custom Order') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($banners->isNotEmpty())
        <section class="space min pb-0">
            <div class="container">
                <div class="row g-3">
                    @foreach ($banners as $banner)
                        <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h5 class="ft-bold mb-1">{{ $banner->title }}</h5>
                                    @if ($banner->message)
                                        <p class="text-muted mb-2">{{ $banner->message }}</p>
                                    @endif
                                    @if ($banner->link_url && $banner->link_text)
                                        <a href="{{ $banner->link_url }}" class="theme-cl ft-medium">
                                            {{ $banner->link_text }}
                                            <i class="fa-light fa-arrow-right ms-1"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="middle">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                    <x-storefront.section-title :eyebrow="__('Trendy Products')" :title="__('Our Trending Products')" />
                </div>
            </div>

            <div class="row align-items-center rows-products">
                @forelse ($featuredProducts as $product)
                    <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                        <x-storefront.product-card :product="$product" :currency="$currency" />
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-light border text-center mb-0">
                            {{ __('No trending products available at the moment.') }}
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="row justify-content-center">
                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                    <div class="position-relative text-center">
                        <a href="{{ route('storefront.catalog.index') }}" class="btn stretched-links borders">
                            {{ __('Explore More') }}
                            <i class="fa-light fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($comboDeals->isNotEmpty())
        <section class="space gray">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <x-storefront.section-title :eyebrow="__('Good Deals')" :title="__('Deals of The Day')" />
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <div class="slide_items">
                            @foreach ($comboDeals as $combo)
                                <x-storefront.combo-card :combo="$combo" :currency="$currency" />
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if (data_get($sections, 'about.content'))
        <section class="space min">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-xl-10 col-lg-11 col-md-12 col-sm-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4 p-md-5">
                                <x-storefront.section-title
                                    :eyebrow="__('About')"
                                    :title="data_get($sections, 'about.title') ?: __('About Us')"
                                    :center="false"
                                />
                                <p class="mb-0 text-muted">{!! nl2br(e(data_get($sections, 'about.content'))) !!}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="px-0 py-3 br-top">
        <div class="container">
            <div class="row">
                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                    <div class="d-flex align-items-center justify-content-start py-2">
                        <div class="d_ico">
                            <i class="fa-light fa-truck-fast"></i>
                        </div>
                        <div class="d_capt">
                            <h5 class="mb-0">{{ __('Flexible Shipping') }}</h5>
                            <span class="text-muted">{{ __('Rates based on destination and cart rules') }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                    <div class="d-flex align-items-center justify-content-start py-2">
                        <div class="d_ico">
                            <i class="fa-light fa-shield-check"></i>
                        </div>
                        <div class="d_capt">
                            <h5 class="mb-0">{{ __('Secure Payments') }}</h5>
                            <span class="text-muted">{{ __('Online checkout protected via gateway verification') }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                    <div class="d-flex align-items-center justify-content-start py-2">
                        <div class="d_ico">
                            <i class="fa-light fa-scissors"></i>
                        </div>
                        <div class="d_capt">
                            <h5 class="mb-0">{{ __('Tailoring Progress') }}</h5>
                            <span class="text-muted">{{ __('Live custom order stage updates') }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                    <div class="d-flex align-items-center justify-content-start py-2">
                        <div class="d_ico">
                            <i class="fa-light fa-headset"></i>
                        </div>
                        <div class="d_capt">
                            <h5 class="mb-0">{{ __('Support') }}</h5>
                            <span class="text-muted">{{ __('Order and fitting help when you need it') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

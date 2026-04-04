@props([
    'combo',
    'currency' => 'TZS',
])

@php
    $comboUrl = route('storefront.catalog.combos.show', ['slug' => $combo->storefrontRouteKey()]);
@endphp

<div class="single_itesm">
    <div class="product_grid card b-0 mb-0">
        <div class="badge bg-info text-white position-absolute ft-regular ab-left text-upper">{{ __('Combo') }}</div>
        <button type="button" class="btn btn_love position-absolute ab-right">
            <i class="fa-light fa-sparkles"></i>
        </button>
        <div class="card-body p-0">
            <div class="shop_thumb position-relative">
                <a class="card-img-top d-block overflow-hidden" href="{{ $comboUrl }}">
                    @if ($combo->featured_image_url)
                        <img class="card-img-top" src="{{ $combo->featured_image_url }}" alt="{{ $combo->name }}">
                    @else
                        <img class="card-img-top" src="{{ asset('frontend/assets/img/product/8.jpg') }}" alt="{{ $combo->name }}">
                    @endif
                </a>
                <div class="product-hover-overlay bg-dark d-flex align-items-center justify-content-center">
                    <div class="edlio">
                        <a href="{{ $comboUrl }}" class="text-white fs-sm ft-medium">
                            <i class="fa-light fa-tags me-1"></i>{{ __('Explore Combo') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer b-0 p-3 pb-0 d-flex align-items-start justify-content-center">
            <div class="text-left">
                <div class="text-center">
                    <h5 class="fw-normal fs-md mb-0 lh-1 mb-1">
                        <a href="{{ $comboUrl }}">{{ $combo->name }}</a>
                    </h5>
                    @if ($combo->description)
                        <p class="small text-muted mb-1">{{ \Illuminate\Support\Str::limit($combo->description, 54) }}</p>
                    @endif
                    <div class="elis_rty">
                        <span class="ft-medium fs-md text-dark">{{ money_currency($combo->price, $currency) }}</span>
                    </div>
                    <div class="small text-muted mt-1">
                        <i class="fa-light fa-layer-group me-1"></i>{{ $combo->items_count }} {{ __('items') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

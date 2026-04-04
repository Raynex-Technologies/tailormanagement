@props([
    'product',
    'currency' => 'TZS',
])

@php
    $productUrl = route('storefront.catalog.show', ['slug' => $product->storefrontRouteKey()]);
    $quickViewModalId = 'quickview-product-'.$product->id;
    $quickViewDesc = trim(strip_tags((string) ($product->short_description ?: $product->full_description)));

    $quickViewGallery = collect();
    if ($product->featured_image_url) {
        $quickViewGallery->push([
            'url' => $product->featured_image_url,
            'alt' => $product->name,
        ]);
    }

    foreach ((array) $product->gallery_images as $path) {
        $imageUrl = \App\Support\StorefrontMedia::url($path);
        if (! $imageUrl || $imageUrl === $product->featured_image_url) {
            continue;
        }

        $quickViewGallery->push([
            'url' => $imageUrl,
            'alt' => $product->name,
        ]);
    }

    if ($quickViewGallery->isEmpty()) {
        $quickViewGallery->push([
            'url' => asset('frontend/assets/img/product/1.jpg'),
            'alt' => $product->name,
        ]);
    }

    $availableSizes = $product->variants
        ->pluck('size')
        ->filter()
        ->map(fn ($size) => strtoupper(trim((string) $size)))
        ->filter()
        ->unique()
        ->values();

    $colorPalette = [
        'black' => '#111827',
        'white' => '#FFFFFF',
        'blue' => '#2563EB',
        'navy' => '#1E3A8A',
        'red' => '#DC2626',
        'green' => '#16A34A',
        'yellow' => '#EAB308',
        'pink' => '#EC4899',
        'purple' => '#7C3AED',
        'gray' => '#6B7280',
        'grey' => '#6B7280',
        'orange' => '#EA580C',
        'brown' => '#92400E',
    ];

    $availableColors = $product->variants
        ->pluck('color')
        ->filter()
        ->map(function ($color) use ($colorPalette) {
            $candidate = trim(strip_tags((string) $color));
            if ($candidate === '') {
                return null;
            }

            if (preg_match('/^#([0-9a-fA-F]{3})$/', $candidate, $matches)) {
                $hex = strtoupper($matches[1]);
                $normalized = '#'.$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];

                return ['label' => $normalized, 'swatch' => $normalized];
            }

            if (preg_match('/^#([0-9a-fA-F]{6})$/', $candidate)) {
                $normalized = '#'.strtoupper(substr($candidate, 1));

                return ['label' => $normalized, 'swatch' => $normalized];
            }

            $normalizedLabel = \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($candidate));
            $swatch = $colorPalette[\Illuminate\Support\Str::lower($candidate)] ?? '#94A3B8';

            return ['label' => $normalizedLabel, 'swatch' => $swatch];
        })
        ->filter()
        ->unique('label')
        ->values();
@endphp

<div class="product_grid card b-0">
    @if ($product->compare_at_price && (float) $product->compare_at_price > (float) $product->default_sell_price)
        <div class="badge bg-sale text-white position-absolute ft-regular ab-left text-upper">{{ __('Sale') }}</div>
    @endif

    <button type="button" class="btn btn_love position-absolute ab-right">
        <i class="fa-light fa-heart"></i>
    </button>

    <div class="card-body p-0">
        <div class="shop_thumb position-relative">
            <button
                type="button"
                class="card-img-top d-block overflow-hidden p-0 border-0 bg-transparent w-100 text-start"
                data-bs-toggle="modal"
                data-bs-target="#{{ $quickViewModalId }}"
                aria-label="{{ __('Quick view for :name', ['name' => $product->name]) }}"
            >
                @if ($product->featured_image_url)
                    <img class="card-img-top" src="{{ $product->featured_image_url }}" alt="{{ $product->name }}">
                @else
                    <img class="card-img-top" src="{{ asset('frontend/assets/img/product/1.jpg') }}" alt="{{ $product->name }}">
                @endif
            </button>
            <div class="product-hover-overlay bg-dark d-flex align-items-center justify-content-center">
                <div class="edlio">
                    <button type="button" class="btn p-0 border-0 bg-transparent text-white fs-sm ft-medium" data-bs-toggle="modal" data-bs-target="#{{ $quickViewModalId }}">
                        <i class="fa-light fa-eye me-1"></i>{{ __('Quick View') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footers b-0 pt-3 px-2 bg-white d-flex align-items-start justify-content-center">
        <div class="text-left">
            <div class="text-center">
                <h5 class="fw-normal fs-md mb-0 lh-1 mb-1">
                    <button type="button" class="btn p-0 border-0 bg-transparent text-body fw-normal fs-md lh-1" data-bs-toggle="modal" data-bs-target="#{{ $quickViewModalId }}">
                        {{ $product->name }}
                    </button>
                </h5>
                <div class="elis_rty">
                    @if ($product->compare_at_price && (float) $product->compare_at_price > (float) $product->default_sell_price)
                        <span class="text-muted ft-medium line-through me-2">{{ money_currency($product->compare_at_price, $currency) }}</span>
                    @endif
                    <span class="ft-medium theme-cl fs-md">{{ money_currency($product->default_sell_price, $currency) }}</span>
                </div>
                <form action="{{ route('storefront.cart.add') }}" method="POST" class="mt-2">
                    @csrf
                    <input type="hidden" name="inventory_item_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3">
                        <i class="fa-light fa-bag-shopping me-1"></i>{{ __('Add') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade lg-modal js-storefront-quickview-modal" id="{{ $quickViewModalId }}" tabindex="-1" aria-labelledby="{{ $quickViewModalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-xl login-pop-form" role="document">
        <div class="modal-content">
            <div class="modal-headers">
                <button type="button" class="border-0 close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}">
                    <span class="ti-close"></span>
                </button>
            </div>

            <div class="modal-body">
                <div class="quick_view_wrap">
                    <div class="quick_view_thmb">
                        <div class="quick_view_slide">
                            @foreach ($quickViewGallery as $image)
                                <div class="single_view_slide">
                                    <img src="{{ $image['url'] }}" class="img-fluid" alt="{{ $image['alt'] }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" />
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="quick_view_capt">
                        <div class="prd_details">
                            @if ($product->category)
                                <div class="prt_01 mb-1">
                                    <span class="text-light bg-info rounded px-2 py-1">{{ $product->category->name }}</span>
                                </div>
                            @endif

                            <div class="prt_02 mb-2">
                                <h2 class="ft-bold mb-1" id="{{ $quickViewModalId }}Label">{{ $product->name }}</h2>
                                <div class="text-left">
                                    <div class="elis_rty">
                                        @if ($product->compare_at_price && (float) $product->compare_at_price > (float) $product->default_sell_price)
                                            <span class="ft-medium text-muted line-through fs-md me-2">{{ money_currency($product->compare_at_price, $currency) }}</span>
                                        @endif
                                        <span class="ft-bold theme-cl fs-lg me-2">{{ money_currency($product->default_sell_price, $currency) }}</span>
                                        @if (($product->stock?->qty_on_hand ?? 0) <= 0 && ! $product->allow_backorders)
                                            <span class="ft-regular text-danger bg-light-danger py-1 px-2 fs-sm">{{ __('Out of Stock') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if ($quickViewDesc !== '')
                                <div class="prt_03 mb-3">
                                    <p>{{ \Illuminate\Support\Str::limit($quickViewDesc, 220) }}</p>
                                </div>
                            @endif

                            @if ($availableColors->isNotEmpty())
                                <div class="prt_04 mb-2">
                                    <p class="d-flex align-items-center mb-0 text-dark ft-medium">{{ __('Color:') }}</p>
                                    <div class="text-left">
                                        @foreach ($availableColors as $colorOption)
                                            <div class="form-check form-option form-check-inline mb-1">
                                                <input class="form-check-input" type="radio" name="quick-view-color-{{ $product->id }}" id="quick-view-color-{{ $product->id }}-{{ $loop->index }}" @checked($loop->first)>
                                                <label class="form-option-label rounded-circle" for="quick-view-color-{{ $product->id }}-{{ $loop->index }}" title="{{ $colorOption['label'] }}">
                                                    <span class="form-option-color rounded-circle" style="background-color: {{ $colorOption['swatch'] }};"></span>
                                                    <span class="visually-hidden">{{ $colorOption['label'] }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($availableSizes->isNotEmpty())
                                <div class="prt_04 mb-4">
                                    <p class="d-flex align-items-center mb-0 text-dark ft-medium">{{ __('Size:') }}</p>
                                    <div class="text-left pb-0 pt-2">
                                        @foreach ($availableSizes as $sizeOption)
                                            <div class="form-check size-option form-option form-check-inline mb-2">
                                                <input class="form-check-input" type="radio" name="quick-view-size-{{ $product->id }}" id="quick-view-size-{{ $product->id }}-{{ $loop->index }}" @checked($loop->first)>
                                                <label class="form-option-label" for="quick-view-size-{{ $product->id }}-{{ $loop->index }}">{{ $sizeOption }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="prt_05 mb-3">
                                <form action="{{ route('storefront.cart.add') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="inventory_item_id" value="{{ $product->id }}">
                                    <div class="form-row row g-3 mb-0">
                                        <div class="col-12 col-md-6 col-lg-3">
                                            <input type="number" name="quantity" value="1" min="1" class="form-control" />
                                        </div>
                                        <div class="col-12 col-md-12 col-lg-5">
                                            <button type="submit" class="btn btn-block custom-height bg-dark mb-2 w-100 text-white">
                                                <i class="fa-light fa-bag-shopping me-2"></i>{{ __('Add to Cart') }}
                                            </button>
                                        </div>
                                        <div class="col-12 col-md-12 col-lg-4">
                                            <a href="{{ $productUrl }}" class="btn custom-height btn-default btn-block mb-2 text-dark w-100">
                                                <i class="fa-light fa-arrow-right me-2"></i>{{ __('View Details') }}
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                if (typeof window.jQuery === 'undefined') {
                    return;
                }

                window.jQuery(document).on('shown.bs.modal', '.js-storefront-quickview-modal', function () {
                    var $slider = window.jQuery(this).find('.quick_view_slide');
                    if (! $slider.length) {
                        return;
                    }

                    if ($slider.hasClass('slick-initialized')) {
                        $slider.slick('setPosition');
                    }
                });
            })();
        </script>
    @endpush
@endonce

@extends('storefront.layouts.app')

@section('title', $product->name)

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('storefront.catalog.index') }}">{{ __('Shop') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .storefront-product-gallery .single_view_slide img {
            width: 100%;
            aspect-ratio: 4/5;
            object-fit: cover;
            border-radius: .35rem;
        }

        .storefront-option-label.is-disabled {
            opacity: .45;
            pointer-events: none;
        }

        .storefront-option-label .form-option-color {
            border: 1px solid rgba(15, 23, 42, .2);
        }
    </style>
@endpush

@section('content')
    @php
        $gallery = collect();
        if ($product->featured_image_path) {
            $gallery->push([
                'path' => $product->featured_image_path,
                'alt' => $product->name,
            ]);
        }
        foreach ($product->media as $media) {
            if ($media->path && $media->path !== $product->featured_image_path) {
                $gallery->push([
                    'path' => $media->path,
                    'alt' => $media->alt_text ?: $product->name,
                ]);
            }
        }
        if ($gallery->isEmpty()) {
            $gallery->push([
                'path' => 'frontend/assets/img/product/1.jpg',
                'alt' => $product->name,
                'public' => true,
            ]);
        }

        $variants = $product->variants->where('is_active', true)->values();
        if ($variants->isEmpty()) {
            $variants = $product->variants->values();
        }

        $normalizeColor = static function (?string $value): ?string {
            $candidate = trim(strip_tags((string) $value));
            if ($candidate === '') {
                return null;
            }

            if (preg_match('/^#([0-9a-fA-F]{3})$/', $candidate, $matches)) {
                $hex = strtoupper($matches[1]);

                return '#'.$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }

            if (preg_match('/^#([0-9a-fA-F]{6})$/', $candidate)) {
                return '#'.strtoupper(substr($candidate, 1));
            }

            return \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($candidate));
        };

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

        $sizeOptions = $variants
            ->pluck('size')
            ->filter()
            ->map(fn ($size) => strtoupper(trim((string) $size)))
            ->filter()
            ->unique()
            ->values();

        $colorOptions = $variants
            ->pluck('color')
            ->filter()
            ->map(function ($color) use ($normalizeColor, $colorPalette) {
                $normalized = $normalizeColor((string) $color);
                if ($normalized === null) {
                    return null;
                }

                if (str_starts_with($normalized, '#')) {
                    return [
                        'value' => $normalized,
                        'label' => $normalized,
                        'swatch' => $normalized,
                    ];
                }

                return [
                    'value' => $normalized,
                    'label' => $normalized,
                    'swatch' => $colorPalette[\Illuminate\Support\Str::lower($normalized)] ?? '#94A3B8',
                ];
            })
            ->filter()
            ->unique('value')
            ->values();

        $variantPayload = $variants
            ->map(function ($variant) use ($normalizeColor) {
                return [
                    'id' => (int) $variant->id,
                    'size' => $variant->size ? strtoupper(trim((string) $variant->size)) : null,
                    'color' => $normalizeColor($variant->color),
                ];
            })
            ->values();

        $defaultVariantId = $variantPayload->first()['id'] ?? null;
        $useVisualVariantOptions = $colorOptions->isNotEmpty() || $sizeOptions->isNotEmpty();
    @endphp

    <section class="middle">
        <div class="container">
            <div class="row justify-content-between align-items-center">
                <div class="col-xl-5 col-lg-6 col-md-12 col-sm-12">
                    <div class="storefront-product-gallery">
                        <div class="quick_view_slide">
                            @foreach ($gallery as $image)
                                @php
                                    $imageUrl = ! empty($image['public'])
                                        ? asset($image['path'])
                                        : asset('storage/'.$image['path']);
                                @endphp
                                <div class="single_view_slide">
                                    <a href="{{ $imageUrl }}" data-lightbox="product-gallery-{{ $product->id }}" class="d-block mb-4">
                                        <img src="{{ $imageUrl }}" class="img-fluid" alt="{{ $image['alt'] }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-xl-7 col-lg-6 col-md-12 col-sm-12">
                    <div class="prd_details ps-3" id="storefront-product-detail-{{ $product->id }}">
                        @if ($product->category)
                            <div class="prt_01 mb-1">
                                <span class="text-light bg-info rounded px-2 py-1">{{ $product->category->name }}</span>
                            </div>
                        @endif

                        <div class="prt_02 mb-3">
                            <h2 class="ft-bold mb-1">{{ $product->name }}</h2>
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

                        @if ($product->short_description)
                            <div class="prt_03 mb-4">
                                <p>{{ $product->short_description }}</p>
                            </div>
                        @endif

                        @if ($colorOptions->isNotEmpty())
                            <div class="prt_04 mb-2">
                                <p class="d-flex align-items-center mb-0 text-dark ft-medium">{{ __('Color:') }}</p>
                                <div class="text-left">
                                    @foreach ($colorOptions as $colorOption)
                                        <div class="form-check form-option form-check-inline mb-1">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                name="product-color-option"
                                                id="product-color-{{ $product->id }}-{{ $loop->index }}"
                                                value="{{ $colorOption['value'] }}"
                                                data-product-color-option
                                                @checked($loop->first)
                                            >
                                            <label class="form-option-label rounded-circle storefront-option-label" for="product-color-{{ $product->id }}-{{ $loop->index }}" title="{{ $colorOption['label'] }}">
                                                <span class="form-option-color rounded-circle" style="background-color: {{ $colorOption['swatch'] }};"></span>
                                                <span class="visually-hidden">{{ $colorOption['label'] }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($sizeOptions->isNotEmpty())
                            <div class="prt_04 mb-4">
                                <p class="d-flex align-items-center mb-0 text-dark ft-medium">{{ __('Size:') }}</p>
                                <div class="text-left pb-0 pt-2">
                                    @foreach ($sizeOptions as $sizeOption)
                                        <div class="form-check size-option form-option form-check-inline mb-2">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                name="product-size-option"
                                                id="product-size-{{ $product->id }}-{{ $loop->index }}"
                                                value="{{ $sizeOption }}"
                                                data-product-size-option
                                                @checked($loop->first)
                                            >
                                            <label class="form-option-label storefront-option-label" for="product-size-{{ $product->id }}-{{ $loop->index }}">{{ $sizeOption }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="prt_04 mb-4">
                            @if ($product->category)
                                <p class="d-flex align-items-center mb-1">{{ __('Category:') }}<strong class="fs-sm text-dark ft-medium ms-1">{{ $product->category->name }}</strong></p>
                            @endif
                            @if ($product->sku)
                                <p class="d-flex align-items-center mb-0">{{ __('SKU:') }}<strong class="fs-sm text-dark ft-medium ms-1">{{ $product->sku }}</strong></p>
                            @endif
                        </div>

                        <form action="{{ route('storefront.cart.add') }}" method="POST" id="storefront-product-form-{{ $product->id }}">
                            @csrf
                            <input type="hidden" name="inventory_item_id" value="{{ $product->id }}">

                            @if ($variants->isNotEmpty())
                                @if ($useVisualVariantOptions)
                                    <input type="hidden" name="inventory_item_variant_id" value="{{ $defaultVariantId }}" data-product-variant-input>
                                @else
                                    <div class="prt_05 mb-3">
                                        <label class="d-block mb-2 ft-medium">{{ __('Variant') }}</label>
                                        <select name="inventory_item_variant_id" class="custom-select">
                                            @foreach ($variants as $variant)
                                                <option value="{{ $variant->id }}">
                                                    {{ $variant->name }}
                                                    @if ((float) $variant->price_delta > 0)
                                                        (+{{ money_currency($variant->price_delta, $currency) }})
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            @endif

                            <div class="prt_05 mb-4">
                                <div class="form-row row g-3 mb-7">
                                    <div class="col-12 col-md-6 col-lg-3">
                                        <input type="number" name="quantity" value="1" min="1" class="form-control" />
                                    </div>
                                    <div class="col-12 col-md-12 col-lg-9">
                                        <button type="submit" class="btn btn-block custom-height bg-dark mb-2 w-100 text-white">
                                            <i class="fa-light fa-bag-shopping me-2"></i>{{ __('Add to Cart') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="middle pt-0">
        <div class="container">
            <div class="row align-items-center justify-content-center">
                <div class="col-xl-11 col-lg-12 col-md-12 col-sm-12">
                    <ul class="nav nav-tabs b-0 d-flex align-items-center justify-content-center simple_tab_links mb-4">
                        <li class="nav-item">
                            <span class="nav-link active">{{ __('Description') }}</span>
                        </li>
                    </ul>
                    <div class="description_info">
                        <p class="p-0 mb-0">{!! nl2br(e($product->full_description ?: __('No detailed description yet.'))) !!}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($relatedProducts->isNotEmpty())
        <section class="middle pt-0">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <x-storefront.section-title :eyebrow="__('Recommended')" :title="__('Related Products')" />
                    </div>
                </div>
                <div class="row align-items-center rows-products">
                    @foreach ($relatedProducts as $related)
                        <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                            <x-storefront.product-card :product="$related" :currency="$currency" />
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection

@if ($variants->isNotEmpty() && $useVisualVariantOptions)
    @push('scripts')
        <script>
            (function () {
                const detailRoot = document.getElementById('storefront-product-detail-{{ $product->id }}');
                if (! detailRoot) {
                    return;
                }

                const variantInput = detailRoot.querySelector('[data-product-variant-input]');
                if (! variantInput) {
                    return;
                }

                const colorInputs = Array.from(detailRoot.querySelectorAll('[data-product-color-option]'));
                const sizeInputs = Array.from(detailRoot.querySelectorAll('[data-product-size-option]'));
                const variants = @json($variantPayload);

                const normalize = (value) => (value || '').toString().trim();

                const selectedValue = (inputs) => {
                    const current = inputs.find((input) => input.checked);
                    return current ? current.value : null;
                };

                const setInputAvailability = (inputs, key, otherValue, otherKey) => {
                    inputs.forEach((input) => {
                        const matches = variants.some((variant) => {
                            const ownMatch = normalize(variant[key]) === normalize(input.value);
                            const otherMatch = !otherValue || normalize(variant[otherKey]) === normalize(otherValue);

                            return ownMatch && otherMatch;
                        });

                        input.disabled = !matches;
                        const label = detailRoot.querySelector('label[for="' + input.id + '"]');
                        if (label) {
                            label.classList.toggle('is-disabled', !matches);
                        }

                        if (!matches && input.checked) {
                            input.checked = false;
                        }
                    });
                };

                const resolveVariant = () => {
                    const selectedColor = selectedValue(colorInputs);
                    const selectedSize = selectedValue(sizeInputs);

                    let candidate = variants.find((variant) => {
                        const colorMatch = !selectedColor || normalize(variant.color) === normalize(selectedColor);
                        const sizeMatch = !selectedSize || normalize(variant.size) === normalize(selectedSize);

                        return colorMatch && sizeMatch;
                    });

                    if (!candidate && selectedColor) {
                        candidate = variants.find((variant) => normalize(variant.color) === normalize(selectedColor));
                    }

                    if (!candidate && selectedSize) {
                        candidate = variants.find((variant) => normalize(variant.size) === normalize(selectedSize));
                    }

                    return candidate || variants[0] || null;
                };

                const syncVariantSelection = () => {
                    const selectedColor = selectedValue(colorInputs);
                    const selectedSize = selectedValue(sizeInputs);

                    if (colorInputs.length && sizeInputs.length) {
                        setInputAvailability(sizeInputs, 'size', selectedColor, 'color');
                        setInputAvailability(colorInputs, 'color', selectedSize, 'size');
                    }

                    const variant = resolveVariant();
                    if (variant) {
                        variantInput.value = variant.id;
                    }
                };

                [...colorInputs, ...sizeInputs].forEach((input) => {
                    input.addEventListener('change', syncVariantSelection);
                });

                syncVariantSelection();
            })();
        </script>
    @endpush
@endif

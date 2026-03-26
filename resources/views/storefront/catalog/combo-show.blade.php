@extends('storefront.layouts.app')

@section('title', $combo->name)

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('storefront.catalog.index') }}">{{ __('Shop') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $combo->name }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .storefront-combo-gallery .single_view_slide img {
            width: 100%;
            aspect-ratio: 4/5;
            object-fit: cover;
            border-radius: .35rem;
        }
    </style>
@endpush

@section('content')
    @php
        $gallery = collect();
        if ($combo->featured_image_path) {
            $gallery->push([
                'path' => $combo->featured_image_path,
                'alt' => $combo->name,
                'storage' => true,
            ]);
        }

        foreach ($comboItems as $comboItem) {
            $product = $comboItem->product;
            if (! $product || ! $product->featured_image_path) {
                continue;
            }

            if ($gallery->contains(fn ($image) => $image['path'] === $product->featured_image_path)) {
                continue;
            }

            $gallery->push([
                'path' => $product->featured_image_path,
                'alt' => $product->name,
                'storage' => true,
            ]);
        }

        if ($gallery->isEmpty()) {
            $gallery->push([
                'path' => 'frontend/assets/img/product/1.jpg',
                'alt' => $combo->name,
                'storage' => false,
            ]);
        }

        $totalItemsCount = (float) $comboItems->sum(fn ($comboItem) => (float) $comboItem->quantity);
    @endphp

    <section class="middle">
        <div class="container">
            <div class="row justify-content-between align-items-center">
                <div class="col-xl-5 col-lg-6 col-md-12 col-sm-12">
                    <div class="storefront-combo-gallery">
                        <div class="quick_view_slide">
                            @foreach ($gallery as $image)
                                @php
                                    $imageUrl = ! empty($image['storage'])
                                        ? asset('storage/'.$image['path'])
                                        : asset($image['path']);
                                @endphp
                                <div class="single_view_slide">
                                    <a href="{{ $imageUrl }}" data-lightbox="combo-gallery-{{ $combo->id }}" class="d-block mb-4">
                                        <img src="{{ $imageUrl }}" class="img-fluid" alt="{{ $image['alt'] }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-xl-7 col-lg-6 col-md-12 col-sm-12">
                    <div class="prd_details ps-3">
                        <div class="prt_01 mb-1">
                            <span class="text-light bg-info rounded px-2 py-1">{{ __('Combo Deal') }}</span>
                        </div>

                        <div class="prt_02 mb-3">
                            <h2 class="ft-bold mb-1">{{ $combo->name }}</h2>
                            <div class="elis_rty">
                                @if ($componentsRegularTotal > $comboPrice)
                                    <span class="ft-medium text-muted line-through fs-md me-2">{{ money_currency($componentsRegularTotal, $currency) }}</span>
                                @endif
                                <span class="ft-bold theme-cl fs-lg me-2">{{ money_currency($comboPrice, $currency) }}</span>
                                @if ($comboSavings > 0)
                                    <span class="ft-regular text-success bg-light-success py-1 px-2 fs-sm">
                                        {{ __('Save :amount', ['amount' => money_currency($comboSavings, $currency)]) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if ($combo->description)
                            <div class="prt_03 mb-4">
                                <p>{{ $combo->description }}</p>
                            </div>
                        @endif

                        <div class="prt_04 mb-4">
                            <p class="d-flex align-items-center mb-2">{{ __('Included Products:') }}</p>
                            <ul class="mb-0 ps-3">
                                @foreach ($comboItems as $comboItem)
                                    @php
                                        $comboItemQty = rtrim(rtrim(number_format((float) $comboItem->quantity, 2, '.', ''), '0'), '.');
                                    @endphp
                                    <li class="mb-2">
                                        <strong>{{ $comboItemQty }}x</strong>
                                        <a href="{{ route('storefront.catalog.show', ['slug' => $comboItem->product->storefrontRouteKey()]) }}">
                                            {{ $comboItem->product->name }}
                                        </a>
                                        @if ($comboItem->product->category)
                                            <span class="text-muted small">({{ $comboItem->product->category->name }})</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="prt_04 mb-4">
                            <p class="d-flex align-items-center mb-0">
                                {{ __('Items in this combo:') }}
                                <strong class="fs-sm text-dark ft-medium ms-1">{{ rtrim(rtrim(number_format($totalItemsCount, 2, '.', ''), '0'), '.') }}</strong>
                            </p>
                        </div>

                        <form action="{{ route('storefront.cart.add') }}" method="POST">
                            @csrf
                            <input type="hidden" name="combo_id" value="{{ $combo->id }}">

                            <div class="prt_05 mb-4">
                                <div class="form-row row g-3 mb-2">
                                    <div class="col-12 col-md-6 col-lg-3">
                                        <input type="number" name="quantity" value="1" min="1" class="form-control" />
                                    </div>
                                    <div class="col-12 col-md-12 col-lg-9">
                                        <button type="submit" class="btn btn-block custom-height bg-dark mb-2 w-100 text-white">
                                            <i class="fa-light fa-bag-shopping me-2"></i>{{ __('Add Combo to Cart') }}
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
                        <p class="p-0 mb-0">{!! nl2br(e($combo->description ?: __('No detailed description yet.'))) !!}</p>
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
                        <x-storefront.section-title :eyebrow="__('Included')" :title="__('Products in This Combo')" />
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

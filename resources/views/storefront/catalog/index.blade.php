@extends('storefront.layouts.app')

@section('title', __('Shop'))

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Shop') }}</li>
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
            <div class="row align-items-center justify-content-between mb-3">
                <div class="col-xl-5 col-lg-5 col-md-12 mb-3 mb-lg-0">
                    <h1 class="ft-medium mb-1">{{ __('Shop Products') }}</h1>
                    <p class="mb-0 text-muted">{{ __('Showing :count products', ['count' => number_format($products->total())]) }}</p>
                </div>
                <div class="col-xl-7 col-lg-7 col-md-12">
                    <form method="GET" action="{{ route('storefront.catalog.index') }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                                <label class="form-label mb-1">{{ __('Search') }}</label>
                                <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="{{ __('Product name or SKU') }}">
                            </div>
                            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                                <label class="form-label mb-1">{{ __('Category') }}</label>
                                <select name="category" class="custom-select">
                                    <option value="">{{ __('All') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->slug }}" @selected($selectedCategory === $category->slug)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                                <label class="form-label mb-1">{{ __('Sort') }}</label>
                                <select name="sort" class="custom-select">
                                    <option value="latest" @selected($sort === 'latest')>{{ __('Latest') }}</option>
                                    <option value="price_asc" @selected($sort === 'price_asc')>{{ __('Price: Low') }}</option>
                                    <option value="price_desc" @selected($sort === 'price_desc')>{{ __('Price: High') }}</option>
                                    <option value="name_asc" @selected($sort === 'name_asc')>{{ __('Name A-Z') }}</option>
                                    <option value="name_desc" @selected($sort === 'name_desc')>{{ __('Name Z-A') }}</option>
                                </select>
                            </div>
                            <div class="col-xl-3 col-lg-3 col-md-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-dark w-100">{{ __('Apply') }}</button>
                                    @if ($search !== '' || $selectedCategory || $sort !== 'latest' || $featuredOnly)
                                        <a href="{{ route('storefront.catalog.index') }}" class="btn btn-outline-dark">{{ __('Reset') }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($featuredOnly)
                            <input type="hidden" name="featured" value="1">
                        @endif
                    </form>
                </div>
            </div>

            @if ($products->isEmpty())
                <div class="alert alert-light border text-center mb-0">
                    {{ __('No products match your current filters.') }}
                </div>
            @else
                <div class="row align-items-center rows-products">
                    @foreach ($products as $product)
                        <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                            <x-storefront.product-card :product="$product" :currency="$currency" />
                        </div>
                    @endforeach
                </div>

                @if ($products->hasPages())
                    <div class="row">
                        <div class="col-xl-12 col-lg-12 col-md-12 d-flex justify-content-center">
                            <div class="mt-3">
                                {{ $products->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </section>
@endsection

@extends('storefront.layouts.app')

@section('title', __('Cart'))

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Cart') }}</li>
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
                <div class="col-xl-8 col-lg-8 col-md-12 mb-3 mb-lg-0">
                    <h1 class="ft-medium mb-0">{{ __('Your Cart') }}</h1>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-12 text-lg-end">
                    <a href="{{ route('storefront.catalog.index') }}" class="btn borders">
                        <i class="fa-light fa-arrow-left me-1"></i>{{ __('Continue Shopping') }}
                    </a>
                </div>
            </div>

            @if ($cart->items->isEmpty())
                <div class="alert alert-light border text-center mb-0">
                    {{ __('Your cart is empty.') }}
                </div>
            @else
                <div class="row align-items-start">
                    <div class="col-xl-8 col-lg-8 col-md-12 col-sm-12">
                        <div class="table-responsive">
                            <table class="table table-borderless">
                                <thead class="gray">
                                    <tr>
                                        <th>{{ __('Product') }}</th>
                                        <th>{{ __('Price') }}</th>
                                        <th>{{ __('Quantity') }}</th>
                                        <th class="text-end">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cart->items as $line)
                                        @php
                                            $lineMeta = (array) ($line->meta ?? []);
                                            $comboMeta = (array) data_get($lineMeta, 'combo', []);
                                            $lineItemName = (string) (data_get($lineMeta, 'item_name') ?: ($line->item?->name ?: __('Unavailable item')));
                                            $lineVariantName = (string) (data_get($lineMeta, 'variant_name') ?: ($line->variant?->name ?? ''));
                                        @endphp
                                        <tr class="br-bottom">
                                            <td>
                                                <p class="mb-0 ft-medium">{{ $lineItemName }}</p>
                                                @if (! empty($comboMeta['name']))
                                                    <span class="text-muted small d-block">{{ __('Combo: :combo', ['combo' => $comboMeta['name']]) }}</span>
                                                @endif
                                                @if ($lineVariantName !== '')
                                                    <span class="text-muted small d-block">{{ __('Variant: :variant', ['variant' => $lineVariantName]) }}</span>
                                                @endif
                                            </td>
                                            <td class="align-middle">{{ money_currency($line->unit_price, $currency) }}</td>
                                            <td class="align-middle">
                                                <form method="POST" action="{{ route('storefront.cart.update', $line) }}" class="d-flex align-items-center gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="number" name="quantity" value="{{ (float) $line->quantity }}" min="1" class="form-control" style="max-width: 90px;">
                                                    <button type="submit" class="btn btn-sm borders">{{ __('Update') }}</button>
                                                </form>
                                            </td>
                                            <td class="align-middle text-end">
                                                <div class="mb-2">{{ money_currency($line->line_total, $currency) }}</div>
                                                <form method="POST" action="{{ route('storefront.cart.remove', $line) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fa-light fa-trash me-1"></i>{{ __('Remove') }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mt-3 mt-lg-0">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h4 class="ft-bold fs-6">{{ __('Order Summary') }}</h4>
                                <div class="d-flex align-items-center justify-content-between py-2 br-bottom">
                                    <span class="text-muted">{{ __('Items') }}</span>
                                    <span>{{ number_format($summary['items_count']) }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between py-2 br-bottom">
                                    <span class="text-muted">{{ __('Subtotal') }}</span>
                                    <span>{{ money_currency($summary['subtotal'], $currency) }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between py-2 br-bottom">
                                    <span class="text-muted">{{ __('Discount') }}</span>
                                    <span>-{{ money_currency($summary['discount'], $currency) }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between py-2">
                                    <strong>{{ __('Total') }}</strong>
                                    <strong>{{ money_currency($summary['total'], $currency) }}</strong>
                                </div>

                                <a href="{{ route('storefront.checkout.index') }}" class="btn btn-dark full-width mt-3">
                                    <i class="fa-light fa-credit-card me-1"></i>{{ __('Proceed to Checkout') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection

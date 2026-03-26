@extends('storefront.layouts.app')

@section('title', __('Checkout'))

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('storefront.cart.index') }}">{{ __('Cart') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Checkout') }}</li>
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
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                    <div class="text-center d-block mb-4">
                        <h2>{{ __('Checkout') }}</h2>
                    </div>
                </div>
            </div>

            <form id="storefront-checkout-form" action="{{ route('storefront.checkout.place') }}" method="POST">
                @csrf

                <div class="row justify-content-between">
                    <div class="col-12 col-lg-8 col-md-12">
                        @error('checkout')
                            <div class="d-none" data-storefront-toast="error">{{ $message }}</div>
                        @enderror

                        <div class="border rounded p-4 mb-4">
                            <h5 class="mb-4 ft-medium">{{ __('Customer Information') }}</h5>
                            <div class="row g-3">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Full Name') }} *</label>
                                        <input
                                            type="text"
                                            name="full_name"
                                            value="{{ old('full_name', $customer?->name ?: auth()->user()?->name) }}"
                                            required
                                            class="form-control"
                                            placeholder="{{ __('Full Name') }}"
                                        />
                                        @error('full_name')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Email') }}</label>
                                        <input
                                            type="email"
                                            name="email"
                                            value="{{ old('email', $customer?->email ?: auth()->user()?->email) }}"
                                            class="form-control"
                                            placeholder="{{ __('Email') }}"
                                        />
                                        @error('email')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Phone') }}</label>
                                        <input
                                            type="text"
                                            name="phone"
                                            value="{{ old('phone', $customer?->phone) }}"
                                            class="form-control"
                                            placeholder="{{ __('Phone') }}"
                                        />
                                        @error('phone')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <p class="text-muted small mb-0">{{ __('Provide at least one contact: email or phone number.') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-4 mb-4">
                            <h5 class="mb-4 ft-medium">{{ __('Shipping Address') }}</h5>
                            <div class="row g-3">
                                <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Country Code') }} *</label>
                                        <input
                                            type="text"
                                            name="shipping_address[country]"
                                            value="{{ old('shipping_address.country', $defaultAddress?->country ?: 'US') }}"
                                            required
                                            class="form-control"
                                            placeholder="{{ __('Country Code') }}"
                                        />
                                        @error('shipping_address.country')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('State / Region') }}</label>
                                        <input
                                            type="text"
                                            name="shipping_address[state]"
                                            value="{{ old('shipping_address.state', $defaultAddress?->state) }}"
                                            class="form-control"
                                            placeholder="{{ __('State / Region') }}"
                                        />
                                    </div>
                                </div>

                                <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('City') }} *</label>
                                        <input
                                            type="text"
                                            name="shipping_address[city]"
                                            value="{{ old('shipping_address.city', $defaultAddress?->city) }}"
                                            required
                                            class="form-control"
                                            placeholder="{{ __('City') }}"
                                        />
                                        @error('shipping_address.city')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Postal Code') }}</label>
                                        <input
                                            type="text"
                                            name="shipping_address[postal_code]"
                                            value="{{ old('shipping_address.postal_code', $defaultAddress?->postal_code) }}"
                                            class="form-control"
                                            placeholder="{{ __('Postal Code') }}"
                                        />
                                    </div>
                                </div>

                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Address Line 1') }} *</label>
                                        <input
                                            type="text"
                                            name="shipping_address[address_line1]"
                                            value="{{ old('shipping_address.address_line1', $defaultAddress?->address_line1) }}"
                                            required
                                            class="form-control"
                                            placeholder="{{ __('Address Line 1') }}"
                                        />
                                        @error('shipping_address.address_line1')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Address Line 2') }}</label>
                                        <input
                                            type="text"
                                            name="shipping_address[address_line2]"
                                            value="{{ old('shipping_address.address_line2', $defaultAddress?->address_line2) }}"
                                            class="form-control"
                                            placeholder="{{ __('Address Line 2') }}"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-4 mb-4">
                            <h5 class="mb-3 ft-medium">{{ __('Billing Address') }}</h5>
                            <div class="form-group mb-3">
                                <input type="hidden" name="billing_same_as_shipping" value="0">
                                <input id="billing_same_as_shipping" class="checkbox-custom" name="billing_same_as_shipping" type="checkbox" value="1" @checked(old('billing_same_as_shipping', true))>
                                <label for="billing_same_as_shipping" class="checkbox-custom-label">{{ __('Billing address is same as shipping') }}</label>
                            </div>

                            <div class="row g-3" id="billing-address-fields">
                                <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Billing Country Code') }}</label>
                                        <input type="text" name="billing_address[country]" value="{{ old('billing_address.country', $defaultAddress?->country ?: 'US') }}" class="form-control" placeholder="{{ __('Country Code') }}" />
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Billing State / Region') }}</label>
                                        <input type="text" name="billing_address[state]" value="{{ old('billing_address.state', $defaultAddress?->state) }}" class="form-control" placeholder="{{ __('State / Region') }}" />
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Billing City') }}</label>
                                        <input type="text" name="billing_address[city]" value="{{ old('billing_address.city', $defaultAddress?->city) }}" class="form-control" placeholder="{{ __('City') }}" />
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Billing Postal Code') }}</label>
                                        <input type="text" name="billing_address[postal_code]" value="{{ old('billing_address.postal_code', $defaultAddress?->postal_code) }}" class="form-control" placeholder="{{ __('Postal Code') }}" />
                                    </div>
                                </div>
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label class="text-dark mb-2">{{ __('Billing Address Line 1') }}</label>
                                        <input type="text" name="billing_address[address_line1]" value="{{ old('billing_address.address_line1', $defaultAddress?->address_line1) }}" class="form-control" placeholder="{{ __('Address Line 1') }}" />
                                    </div>
                                </div>
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="form-group mb-0">
                                        <label class="text-dark mb-2">{{ __('Billing Address Line 2') }}</label>
                                        <input type="text" name="billing_address[address_line2]" value="{{ old('billing_address.address_line2', $defaultAddress?->address_line2) }}" class="form-control" placeholder="{{ __('Address Line 2') }}" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-4 mb-4">
                            <h5 class="mb-4 ft-medium">{{ __('Shipping Method') }}</h5>
                            @forelse ($shippingQuotes as $quote)
                                <label class="d-flex align-items-center justify-content-between border rounded px-3 py-3 mb-2">
                                    <span class="d-flex align-items-start">
                                        <input
                                            type="radio"
                                            name="shipping_method_code"
                                            value="{{ $quote['code'] }}"
                                            @checked(old('shipping_method_code', $loop->first ? $quote['code'] : null) === $quote['code'])
                                            class="me-2 mt-1"
                                        />
                                        <span>
                                            <span class="ft-medium d-block">{{ $quote['name'] }}</span>
                                            @if ($quote['estimated_delivery_window'])
                                                <span class="text-muted small">{{ $quote['estimated_delivery_window'] }}</span>
                                            @endif
                                        </span>
                                    </span>
                                    <span class="ft-medium">{{ money_currency($quote['amount'], $quote['currency']) }}</span>
                                </label>
                            @empty
                                <p class="text-danger mb-0">{{ __('No shipping methods available for your destination.') }}</p>
                            @endforelse
                            @error('shipping_method_code')<p class="text-danger small mb-0 mt-2">{{ $message }}</p>@enderror
                        </div>

                        <div class="border rounded p-4 mb-4">
                            <h5 class="mb-4 ft-medium">{{ __('Payment Method') }}</h5>
                            @forelse ($paymentMethods as $method)
                                <label class="d-flex align-items-center justify-content-between border rounded px-3 py-3 mb-2">
                                    <span class="d-flex align-items-start">
                                        <input
                                            type="radio"
                                            name="payment_method_code"
                                            value="{{ $method->code }}"
                                            data-payment-code="{{ strtolower((string) $method->code) }}"
                                            data-payment-online="{{ $method->is_online ? '1' : '0' }}"
                                            @checked(old('payment_method_code', $loop->first ? $method->code : null) === $method->code)
                                            class="me-2 mt-1"
                                        />
                                        <span>
                                            <span class="ft-medium d-block">{{ $method->name }}</span>
                                            @if ($method->description)
                                                <span class="text-muted small">{{ $method->description }}</span>
                                            @endif
                                        </span>
                                    </span>
                                    <span class="badge bg-light text-dark">{{ $method->is_online ? __('Online') : __('Offline') }}</span>
                                </label>
                            @empty
                                <p class="text-danger mb-0">{{ __('No payment methods available.') }}</p>
                            @endforelse
                            @error('payment_method_code')<p class="text-danger small mb-0 mt-2">{{ $message }}</p>@enderror
                        </div>

                        <div class="border rounded p-4 mb-4">
                            <h5 class="mb-4 ft-medium">{{ __('Coupon') }}</h5>
                            <div class="form-group mb-0">
                                <label class="text-dark mb-2">{{ __('Coupon Code') }}</label>
                                <input
                                    type="text"
                                    name="coupon_code"
                                    value="{{ old('coupon_code') }}"
                                    class="form-control text-uppercase"
                                    placeholder="{{ __('e.g. WELCOME10') }}"
                                />
                                @error('coupon_code')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
                                <p class="text-muted small mt-2 mb-0">{{ __('Discount is validated and applied when you place the order.') }}</p>
                            </div>
                        </div>

                        <div class="border rounded p-4 mb-4">
                            <h5 class="mb-4 ft-medium">{{ __('Order Notes') }}</h5>
                            <div class="form-group mb-0">
                                <textarea name="notes" rows="3" class="form-control" placeholder="{{ __('Any notes for your order?') }}">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4 col-md-12">
                        <div class="border rounded mb-4">
                            <div class="px-3 py-3 gray">
                                <h5 class="mb-0 ft-medium">{{ __('Order Summary') }}</h5>
                            </div>

                            <div class="px-3 py-3">
                                @forelse ($cart->items as $line)
                                    @php
                                        $lineMeta = (array) ($line->meta ?? []);
                                        $comboMeta = (array) data_get($lineMeta, 'combo', []);
                                        $lineItemName = (string) (data_get($lineMeta, 'item_name') ?: ($line->item?->name ?: __('Item')));
                                    @endphp
                                    <div @class([
                                        'd-flex align-items-center justify-content-between py-2',
                                        'br-bottom' => ! $loop->last,
                                    ])>
                                        <div class="pe-2">
                                            <p class="mb-0 ft-medium">{{ $lineItemName }}</p>
                                            @if (! empty($comboMeta['name']))
                                                <span class="text-muted small d-block">{{ __('Combo: :combo', ['combo' => $comboMeta['name']]) }}</span>
                                            @endif
                                            <span class="text-muted small">{{ __('Qty :qty', ['qty' => (float) $line->quantity]) }}</span>
                                        </div>
                                        <span class="ft-medium">{{ money_currency($line->line_total, $currency) }}</span>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">{{ __('Your cart is empty.') }}</p>
                                @endforelse
                            </div>

                            <div class="px-3 py-3 br-top">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-muted">{{ __('Subtotal') }}</span>
                                    <span>{{ money_currency($summary['subtotal'], $currency) }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="text-muted">{{ __('Discount') }}</span>
                                    <span>-{{ money_currency($summary['discount'], $currency) }}</span>
                                </div>
                            </div>
                        </div>

                        <button id="storefront-checkout-submit" type="submit" class="btn btn-dark full-width mb-3">
                            <i class="fa-light fa-lock me-2"></i>{{ __('Checkout') }}
                        </button>
                        <a href="{{ route('storefront.cart.index') }}" class="btn btn-dark-light full-width">{{ __('Back to Cart') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (function () {
            var checkoutForm = document.getElementById('storefront-checkout-form');
            var submitButton = document.getElementById('storefront-checkout-submit');
            var toggle = document.getElementById('billing_same_as_shipping');
            var fields = document.getElementById('billing-address-fields');

            if (toggle && fields) {
                var sync = function () {
                    fields.style.display = toggle.checked ? 'none' : '';
                };

                toggle.addEventListener('change', sync);
                sync();
            }

            if (!checkoutForm) {
                return;
            }

            checkoutForm.addEventListener('submit', function (event) {
                if (checkoutForm.dataset.submitting === '1') {
                    event.preventDefault();

                    return;
                }

                if (typeof checkoutForm.checkValidity === 'function' && !checkoutForm.checkValidity()) {
                    return;
                }

                var selectedMethod = checkoutForm.querySelector('input[name="payment_method_code"]:checked');
                var isPesapal = false;
                var isOnline = false;

                if (selectedMethod) {
                    var code = String(selectedMethod.getAttribute('data-payment-code') || '').toLowerCase();
                    isOnline = selectedMethod.getAttribute('data-payment-online') === '1';
                    isPesapal = code === 'pesapal' && isOnline;
                } else {
                    return;
                }

                if (window.storefrontToast) {
                    if (isPesapal) {
                        window.storefrontToast('{{ __('Processing payment. You will be redirected shortly...') }}', 'info', 3500);
                    } else {
                        window.storefrontToast('{{ __('Order will be checked out when payment is confirmed.') }}', 'info', 4500);
                    }
                }

                checkoutForm.dataset.submitting = '1';

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.classList.add('disabled');
                }

                var submitResetTimeout = window.setTimeout(function () {
                    if (checkoutForm.dataset.submitting !== '1') {
                        return;
                    }

                    checkoutForm.dataset.submitting = '';
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.classList.remove('disabled');
                    }

                    if (window.storefrontToast) {
                        window.storefrontToast('{{ __('Checkout is taking longer than expected. Please try again.') }}', 'warning', 6000);
                    }
                }, 20000);

                window.addEventListener('pagehide', function () {
                    window.clearTimeout(submitResetTimeout);
                }, {
                    once: true
                });
            });
        })();
    </script>
@endpush

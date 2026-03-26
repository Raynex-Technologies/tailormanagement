@extends('storefront.layouts.app')

@section('title', __('Addresses'))

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('storefront.account.dashboard') }}">{{ __('Dashboard') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Addresses') }}</li>
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
            <div class="row align-items-start justify-content-between">
                <div class="col-12 col-md-12 col-lg-4 col-xl-4 text-center miliods mb-4 mb-lg-0">
                    <x-storefront.account-sidebar :customer="$customer" :settings="$settings" active="addresses" />
                </div>

                <div class="col-12 col-md-12 col-lg-8 col-xl-8">
                    <div class="ord_list_wrap border mb-4">
                        <div class="ord_list_head gray px-3 py-3">
                            <h6 class="mb-0 ft-medium">{{ __('Add New Address') }}</h6>
                        </div>
                        <div class="ord_list_body text-left px-3 py-4">
                            <form action="{{ route('storefront.account.addresses.store') }}" method="POST" class="row g-3 m-0">
                                @csrf
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label class="small text-dark ft-medium mb-2">{{ __('Label') }}</label>
                                        <input type="text" name="label" class="form-control" value="{{ old('label') }}">
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label class="small text-dark ft-medium mb-2">{{ __('Recipient Name') }} *</label>
                                        <input type="text" name="recipient_name" required class="form-control" value="{{ old('recipient_name') }}">
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label class="small text-dark ft-medium mb-2">{{ __('Phone') }}</label>
                                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label class="small text-dark ft-medium mb-2">{{ __('Country Code') }} *</label>
                                        <input type="text" name="country" required class="form-control" value="{{ old('country', 'US') }}">
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label class="small text-dark ft-medium mb-2">{{ __('State') }}</label>
                                        <input type="text" name="state" class="form-control" value="{{ old('state') }}">
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label class="small text-dark ft-medium mb-2">{{ __('City') }} *</label>
                                        <input type="text" name="city" required class="form-control" value="{{ old('city') }}">
                                    </div>
                                </div>
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label class="small text-dark ft-medium mb-2">{{ __('Address Line 1') }} *</label>
                                        <input type="text" name="address_line1" required class="form-control" value="{{ old('address_line1') }}">
                                    </div>
                                </div>
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label class="small text-dark ft-medium mb-2">{{ __('Address Line 2') }}</label>
                                        <input type="text" name="address_line2" class="form-control" value="{{ old('address_line2') }}">
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label class="small text-dark ft-medium mb-2">{{ __('Postal Code') }}</label>
                                        <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code') }}">
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                    <div class="form-group h-100 d-flex flex-column justify-content-end">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="new-default-shipping" name="is_default_shipping" value="1" @checked(old('is_default_shipping'))>
                                            <label class="form-check-label" for="new-default-shipping">{{ __('Default shipping') }}</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="new-default-billing" name="is_default_billing" value="1" @checked(old('is_default_billing'))>
                                            <label class="form-check-label" for="new-default-billing">{{ __('Default billing') }}</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                    <div class="form-group mb-0">
                                        <button type="submit" class="btn btn-dark">{{ __('Save Address') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    @forelse ($addresses as $address)
                        <div class="ord_list_wrap border mb-4">
                            <div class="ord_list_head gray d-flex align-items-center justify-content-between px-3 py-3">
                                <div class="olh_flex text-start">
                                    <h6 class="mb-0 ft-medium">{{ $address->label ?: __('Address') }}</h6>
                                </div>
                                <div class="olh_flex text-end">
                                    @if ($address->is_default_shipping)
                                        <span class="badge bg-light-success text-success me-1">{{ __('Default Shipping') }}</span>
                                    @endif
                                    @if ($address->is_default_billing)
                                        <span class="badge bg-light-warning text-warning">{{ __('Default Billing') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="ord_list_body text-left px-3 py-4">
                                <form action="{{ route('storefront.account.addresses.update', $address) }}" method="POST" class="row g-3 m-0">
                                    @csrf
                                    @method('PATCH')
                                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                        <div class="form-group">
                                            <label class="small text-dark ft-medium mb-2">{{ __('Label') }}</label>
                                            <input type="text" name="label" class="form-control" value="{{ $address->label }}">
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                        <div class="form-group">
                                            <label class="small text-dark ft-medium mb-2">{{ __('Recipient Name') }} *</label>
                                            <input type="text" name="recipient_name" required class="form-control" value="{{ $address->recipient_name }}">
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                        <div class="form-group">
                                            <label class="small text-dark ft-medium mb-2">{{ __('Phone') }}</label>
                                            <input type="text" name="phone" class="form-control" value="{{ $address->phone }}">
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                        <div class="form-group">
                                            <label class="small text-dark ft-medium mb-2">{{ __('Country Code') }} *</label>
                                            <input type="text" name="country" required class="form-control" value="{{ $address->country }}">
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                        <div class="form-group">
                                            <label class="small text-dark ft-medium mb-2">{{ __('State') }}</label>
                                            <input type="text" name="state" class="form-control" value="{{ $address->state }}">
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                        <div class="form-group">
                                            <label class="small text-dark ft-medium mb-2">{{ __('City') }} *</label>
                                            <input type="text" name="city" required class="form-control" value="{{ $address->city }}">
                                        </div>
                                    </div>
                                    <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                        <div class="form-group">
                                            <label class="small text-dark ft-medium mb-2">{{ __('Address Line 1') }} *</label>
                                            <input type="text" name="address_line1" required class="form-control" value="{{ $address->address_line1 }}">
                                        </div>
                                    </div>
                                    <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                        <div class="form-group">
                                            <label class="small text-dark ft-medium mb-2">{{ __('Address Line 2') }}</label>
                                            <input type="text" name="address_line2" class="form-control" value="{{ $address->address_line2 }}">
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                        <div class="form-group">
                                            <label class="small text-dark ft-medium mb-2">{{ __('Postal Code') }}</label>
                                            <input type="text" name="postal_code" class="form-control" value="{{ $address->postal_code }}">
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
                                        <div class="form-group h-100 d-flex flex-column justify-content-end">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="shipping-{{ $address->id }}" name="is_default_shipping" value="1" @checked($address->is_default_shipping)>
                                                <label class="form-check-label" for="shipping-{{ $address->id }}">{{ __('Default shipping') }}</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="billing-{{ $address->id }}" name="is_default_billing" value="1" @checked($address->is_default_billing)>
                                                <label class="form-check-label" for="billing-{{ $address->id }}">{{ __('Default billing') }}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                        <div class="form-group d-flex align-items-center gap-2 mb-0">
                                            <button type="submit" class="btn btn-dark">{{ __('Update') }}</button>
                                        </div>
                                    </div>
                                </form>

                                <form action="{{ route('storefront.account.addresses.delete', $address) }}" method="POST" class="mt-3">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="ord_list_wrap border">
                            <div class="ord_list_body text-left px-4 py-4">
                                <p class="mb-0 text-muted">{{ __('No addresses saved yet.') }}</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection

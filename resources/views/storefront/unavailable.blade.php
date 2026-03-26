@extends('storefront.layouts.app')

@section('title', __('Storefront Unavailable'))

@section('content')
    <section class="middle">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-7 col-lg-8 col-md-10 col-sm-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center p-5">
                            <h1 class="ft-bold mb-3">{{ __('Storefront Unavailable') }}</h1>
                            <p class="text-muted mb-4">
                                {{ $message ?: __('Our online storefront is temporarily unavailable. Please check back shortly.') }}
                            </p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <a href="{{ route('login') }}" class="btn borders">{{ __('Staff Login') }}</a>
                                <a href="{{ route('health') }}" class="btn btn-dark">{{ __('System Status') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

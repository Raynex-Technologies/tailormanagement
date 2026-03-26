@extends('storefront.layouts.app')

@section('title', $page->meta_title ?: $page->title)

@section('breadcrumbs')
    <div class="gray py-3">
        <div class="container">
            <div class="row">
                <div class="col-xl-12 col-lg-12 col-md-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $page->title }}</li>
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
            <article class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="ft-bold mb-2">{{ $page->title }}</h1>
                    @if ($page->excerpt)
                        <p class="text-muted mb-4">{{ $page->excerpt }}</p>
                    @endif
                    <div class="description_info">
                        <p class="p-0 mb-0">{!! nl2br(e($page->body ?? '')) !!}</p>
                    </div>
                </div>
            </article>
        </div>
    </section>
@endsection

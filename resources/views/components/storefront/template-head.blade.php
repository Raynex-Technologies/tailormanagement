@props([
    'settings' => null,
    'title' => null,
])

@php
    $settings = $settings ?? \App\Models\BusinessSetting::instance();
    $pageTitle = $title ?: ($settings->storefront_seo_title ?: ($settings->business_name ?: config('app.name')));
@endphp

@include('partials.head', ['title' => $pageTitle])

@if ($settings->storefront_favicon_url)
    <link rel="icon" href="{{ $settings->storefront_favicon_url }}" sizes="any">
@endif

@if ($settings->storefront_seo_description)
    <meta name="description" content="{{ $settings->storefront_seo_description }}">
@endif

<link href="{{ asset('frontend/assets/css/styles.css') }}" rel="stylesheet">

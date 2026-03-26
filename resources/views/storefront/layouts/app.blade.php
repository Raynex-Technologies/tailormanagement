<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $settings = $settings ?? \App\Models\BusinessSetting::instance();
            $pageTitle = trim((string) $__env->yieldContent('title'));
        @endphp

        <x-storefront.template-head :settings="$settings" :title="$pageTitle !== '' ? $pageTitle : null" />
        @stack('styles')
    </head>
    <body>
        @php
            $headerMenu = null;
            $footerMenu = null;

            $cmsMenuTablesReady = false;
            try {
                $cmsMenuTablesReady = \Illuminate\Support\Facades\Schema::hasTable('menus')
                    && \Illuminate\Support\Facades\Schema::hasTable('menu_items');
            } catch (\Throwable $e) {
                $cmsMenuTablesReady = false;
            }

            if ($cmsMenuTablesReady) {
                $headerMenu = \App\Models\Menu::query()
                    ->where('location', 'header')
                    ->where('is_active', true)
                    ->with(['items' => fn ($query) => $query->where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->orderBy('id')])
                    ->first();

                $footerMenu = \App\Models\Menu::query()
                    ->where('location', 'footer')
                    ->where('is_active', true)
                    ->with(['items' => fn ($query) => $query->where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->orderBy('id')])
                    ->first();
            }

            $headerMenuLinks = $headerMenu?->items?->map(fn ($item) => [
                'label' => $item->label,
                'url' => $item->resolved_url,
                'target' => $item->target ?: '_self',
            ]) ?: collect();

            if ($headerMenuLinks->isEmpty()) {
                $headerMenuLinks = ($headerPages ?? collect())->map(fn ($page) => [
                    'label' => $page->title,
                    'url' => route('storefront.page', $page->slug),
                    'target' => '_self',
                ]);
            }

            $footerMenuLinks = $footerMenu?->items?->map(fn ($item) => [
                'label' => $item->label,
                'url' => $item->resolved_url,
                'target' => $item->target ?: '_self',
            ]) ?: collect();

            if ($footerMenuLinks->isEmpty()) {
                $footerMenuLinks = ($footerPages ?? collect())->map(fn ($page) => [
                    'label' => $page->title,
                    'url' => route('storefront.page', $page->slug),
                    'target' => '_self',
                ]);
            }

            $guestToken = app(\App\Services\Storefront\CartService::class)->guestTokenFromRequest(request());
            $activeCart = \App\Models\Cart::query()
                ->when(auth()->check(), fn ($query) => $query->where('user_id', auth()->id()))
                ->when(! auth()->check() && filled($guestToken), fn ($query) => $query->where('token', $guestToken)->whereNull('user_id'))
                ->with('items')
                ->latest('id')
                ->first();

            $cartItemsCount = (int) ($activeCart?->items->sum('quantity') ?? 0);
        @endphp

        <div class="preloader"></div>

        <div id="main-wrapper">
            @if ($settings->storefront_announcement_bar_enabled && filled($settings->storefront_announcement_text))
                <div class="bg-dark py-2">
                    <div class="container text-center text-white small">
                        <i class="fa-light fa-megaphone me-1"></i>
                        {{ $settings->storefront_announcement_text }}
                        @if ($settings->storefront_announcement_link)
                            <a href="{{ $settings->storefront_announcement_link }}" class="text-white text-decoration-underline ms-1">
                                {{ __('Learn more') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            <x-storefront.template-header :settings="$settings" :header-menu-links="$headerMenuLinks" :cart-items-count="$cartItemsCount" />

            @if (session('success'))
                <div class="d-none" data-storefront-toast="success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="d-none" data-storefront-toast="error">{{ session('error') }}</div>
            @endif
            @if (session('warning'))
                <div class="d-none" data-storefront-toast="warning">{{ session('warning') }}</div>
            @endif
            @if (session('info'))
                <div class="d-none" data-storefront-toast="info">{{ session('info') }}</div>
            @endif

            @yield('breadcrumbs')
            {{ $slot ?? '' }}
            @yield('content')

            <x-storefront.template-footer :settings="$settings" :footer-menu-links="$footerMenuLinks" />
        </div>

        <x-storefront.template-scripts />
    </body>
</html>

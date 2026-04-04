<?php

namespace App\Http\Middleware;

use App\Services\Storefront\StorefrontContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStorefrontCheckoutEnabled
{
    public function __construct(
        protected StorefrontContext $storefrontContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->storefrontContext->isStorefrontEnabled()) {
            $message = $this->storefrontContext->settings()->storefront_maintenance_message
                ?: 'The storefront is currently unavailable.';

            return redirect()
                ->route('dashboard')
                ->with('error', $message);
        }

        if ($this->storefrontContext->isCatalogMode()) {
            return redirect()
                ->route('storefront.cart.index')
                ->with('error', 'Checkout is temporarily disabled while catalog mode is active.');
        }

        return $next($request);
    }
}

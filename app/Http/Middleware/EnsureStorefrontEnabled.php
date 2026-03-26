<?php

namespace App\Http\Middleware;

use App\Services\Storefront\StorefrontContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStorefrontEnabled
{
    public function __construct(
        protected StorefrontContext $storefrontContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->storefrontContext->isStorefrontEnabled()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->storefrontContext->settings()->storefront_maintenance_message
                    ?: 'The storefront is currently unavailable.',
            ], 503);
        }

        return response()->view('storefront.unavailable', [
            'settings' => $this->storefrontContext->settings(),
            'message' => $this->storefrontContext->settings()->storefront_maintenance_message,
        ], 503);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\Storefront\CartService;
use App\Services\Storefront\StorefrontContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncGuestCart
{
    public function __construct(
        protected CartService $cartService,
        protected StorefrontContext $storefrontContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $guestToken = $this->cartService->guestTokenFromRequest($request);

            if ($guestToken) {
                $this->cartService->resolveCart($user, $guestToken, $this->storefrontContext->currency());
                $this->cartService->clearGuestTokenCookie();
            }
        }

        return $next($request);
    }
}

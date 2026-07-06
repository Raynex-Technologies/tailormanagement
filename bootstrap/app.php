<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureStorefrontCheckoutEnabled;
use App\Http\Middleware\EnsureStorefrontEnabled;
use App\Http\Middleware\SetBranchContext;
use App\Http\Middleware\SyncGuestCart;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware alias
        $middleware->alias([
            'branch.context' => SetBranchContext::class,
            'module.enabled' => EnsureModuleEnabled::class,
            'storefront.enabled' => EnsureStorefrontEnabled::class,
            'storefront.checkout' => EnsureStorefrontCheckoutEnabled::class,
            'storefront.cart.sync' => SyncGuestCart::class,
        ]);

        // Apply throttling to web routes
        $middleware->web(append: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':web',
            SyncGuestCart::class,
            AddSecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render custom error pages in production
        $exceptions->render(function (HttpException $e, Request $request) {
            if (! app()->hasDebugModeEnabled()) {
                $status = $e->getStatusCode();

                if (view()->exists("errors.{$status}")) {
                    return response()->view("errors.{$status}", [
                        'exception' => $e,
                    ], $status);
                }
            }

            return null;
        });

        // Don't report common HTTP exceptions
        $exceptions->dontReport([
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
            \Illuminate\Session\TokenMismatchException::class,
            \Illuminate\Validation\ValidationException::class,
        ]);
    })->create();

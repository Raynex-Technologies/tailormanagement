<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class StorefrontLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        $target = config('fortify.home');

        if ($user && $user->hasRole('customer')) {
            $target = route('storefront.account.dashboard');
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false])
            : redirect()->intended($target);
    }
}

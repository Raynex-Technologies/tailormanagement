<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class StorefrontRegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        $target = config('fortify.home');

        if ($user && $user->hasRole('customer')) {
            $target = route('storefront.account.dashboard');
        }

        return redirect()->intended($target);
    }
}

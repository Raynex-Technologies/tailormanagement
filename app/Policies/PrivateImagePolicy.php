<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PrivateImage;

class PrivateImagePolicy
{
    public function view(User $user, PrivateImage $privateImage): bool
    {
        return match ($privateImage->scope) {
            'orders' => $user->can('orders.view'),
            'expenses' => $user->can('expenses.view'),
            'installments' => $user->can('installments.view'),
            'storefront' => $user->can('storefront.catalog.manage') || $user->can('storefront.settings.manage'),
            default => $user->can('orders.view')
                || $user->can('storefront.catalog.manage')
                || $user->can('roles.manage'),
        };
    }
}

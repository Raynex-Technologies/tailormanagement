<?php

namespace App\Policies;

use App\Models\PosSale;
use App\Models\User;
use App\Support\SalesPermissions;

class PosSalePolicy
{
    public function viewAny(User $user): bool
    {
        return SalesPermissions::canViewAny($user);
    }

    public function view(User $user, PosSale $sale): bool
    {
        return SalesPermissions::canView($user, $sale);
    }
}

<?php

namespace App\Policies;

use App\Models\User;

class GarmentOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('garment-options.view');
    }

    public function view(User $user): bool
    {
        return $user->can('garment-options.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('garment-options.manage');
    }
}

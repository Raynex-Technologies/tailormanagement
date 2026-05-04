<?php

namespace App\Policies;

use App\Models\User;

class AvailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('availability.view');
    }

    public function view(User $user): bool
    {
        return $user->can('availability.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('availability.manage');
    }
}

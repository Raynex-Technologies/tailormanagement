<?php

namespace App\Policies;

use App\Models\CapitalAllocation;
use App\Models\User;

class CapitalAllocationPolicy
{
    /**
     * Determine whether the user can view any allocations.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('capital.view');
    }

    /**
     * Determine whether the user can view the allocation.
     */
    public function view(User $user, CapitalAllocation $allocation): bool
    {
        if (! $user->can('capital.view')) {
            return false;
        }

        // Global admins can view any
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Others can only view allocations in their branch
        return $user->canAccessBranch($allocation->branch_id);
    }

    /**
     * Determine whether the user can assign allocations.
     */
    public function assign(User $user): bool
    {
        return $user->can('capital.assign');
    }

    /**
     * Determine whether the user can close the allocation.
     */
    public function close(User $user, CapitalAllocation $allocation): bool
    {
        if (! $user->can('capital.close')) {
            return false;
        }

        // Global admins can close any
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Others can only close allocations in their branch
        return $user->canAccessBranch($allocation->branch_id);
    }
}

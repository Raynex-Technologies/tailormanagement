<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    /**
     * Determine whether the user can view any branches.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('branches.view');
    }

    /**
     * Determine whether the user can view the branch.
     */
    public function view(User $user, Branch $branch): bool
    {
        // Global admins can view any branch
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Users can view their own branch
        return $user->branch_id === $branch->id;
    }

    /**
     * Determine whether the user can create branches.
     */
    public function create(User $user): bool
    {
        return $user->can('branches.manage');
    }

    /**
     * Determine whether the user can update the branch.
     */
    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branches.manage');
    }

    /**
     * Determine whether the user can delete the branch.
     */
    public function delete(User $user, Branch $branch): bool
    {
        // Only superadmins can delete branches
        return $user->hasRole('superadmin');
    }
}

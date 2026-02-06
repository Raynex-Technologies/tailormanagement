<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can view the target user.
     */
    public function view(User $user, User $targetUser): bool
    {
        if (! $user->can('users.view')) {
            return false;
        }

        // Global admins can view any user
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch managers can only view users in their branch
        if ($user->isBranchManager()) {
            // Cannot view global admins
            if ($targetUser->isGlobalAdmin()) {
                return false;
            }

            return $targetUser->branch_id === $user->branch_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('users.manage') && ($user->isGlobalAdmin() || $user->isBranchManager());
    }

    /**
     * Determine whether the user can update the target user.
     */
    public function update(User $user, User $targetUser): bool
    {
        if (! $user->can('users.manage')) {
            return false;
        }

        // Global admins can update any user
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch managers have limited update rights
        if ($user->isBranchManager()) {
            // Cannot edit global admins (admin/superadmin)
            if ($targetUser->isGlobalAdmin()) {
                return false;
            }

            // Can only edit users in their branch
            return $targetUser->branch_id === $user->branch_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the target user.
     */
    public function delete(User $user, User $targetUser): bool
    {
        if (! $user->can('users.manage')) {
            return false;
        }

        // Cannot delete yourself
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Global admins can delete any user except themselves
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch managers can only delete users in their branch (not admins)
        if ($user->isBranchManager()) {
            if ($targetUser->isGlobalAdmin()) {
                return false;
            }

            return $targetUser->branch_id === $user->branch_id;
        }

        return false;
    }

    /**
     * Determine whether the user can assign a specific role.
     */
    public function assignRole(User $user, string $role): bool
    {
        if (! $user->can('users.manage')) {
            return false;
        }

        // Only global admins can assign admin/superadmin roles
        $globalRoles = ['admin', 'superadmin'];
        if (in_array($role, $globalRoles)) {
            return $user->isGlobalAdmin();
        }

        // Branch managers and admins can assign other roles
        return $user->isGlobalAdmin() || $user->isBranchManager();
    }

    /**
     * Get the list of roles the user can assign.
     */
    public static function assignableRoles(User $user): array
    {
        if ($user->isGlobalAdmin()) {
            return ['superadmin', 'admin', 'branch_manager', 'accountant', 'storekeeper', 'tailor', 'sales'];
        }

        if ($user->isBranchManager()) {
            return ['branch_manager', 'accountant', 'storekeeper', 'tailor', 'sales'];
        }

        return [];
    }
}

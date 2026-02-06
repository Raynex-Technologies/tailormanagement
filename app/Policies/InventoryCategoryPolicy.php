<?php

namespace App\Policies;

use App\Models\InventoryCategory;
use App\Models\User;

class InventoryCategoryPolicy
{
    /**
     * Determine whether the user can view any inventory categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    /**
     * Determine whether the user can view the inventory category.
     */
    public function view(User $user, InventoryCategory $category): bool
    {
        if (! $user->can('inventory.view')) {
            return false;
        }

        // Global admins can view any category
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($category->branch_id);
    }

    /**
     * Determine whether the user can create inventory categories.
     */
    public function create(User $user): bool
    {
        return $user->can('inventory.items.manage');
    }

    /**
     * Determine whether the user can update the inventory category.
     */
    public function update(User $user, InventoryCategory $category): bool
    {
        if (! $user->can('inventory.items.manage')) {
            return false;
        }

        // Global admins can update any category
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($category->branch_id);
    }

    /**
     * Determine whether the user can delete the inventory category.
     */
    public function delete(User $user, InventoryCategory $category): bool
    {
        if (! $user->can('inventory.items.manage')) {
            return false;
        }

        // Global admins can delete any category
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($category->branch_id);
    }
}

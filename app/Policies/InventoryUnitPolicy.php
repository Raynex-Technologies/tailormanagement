<?php

namespace App\Policies;

use App\Models\InventoryUnit;
use App\Models\User;

class InventoryUnitPolicy
{
    /**
     * Determine whether the user can view any inventory units.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    /**
     * Determine whether the user can view the inventory unit.
     */
    public function view(User $user, InventoryUnit $unit): bool
    {
        if (! $user->can('inventory.view')) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($unit->branch_id);
    }

    /**
     * Determine whether the user can create inventory units.
     */
    public function create(User $user): bool
    {
        return $user->can('inventory.items.manage');
    }

    /**
     * Determine whether the user can update the inventory unit.
     */
    public function update(User $user, InventoryUnit $unit): bool
    {
        if (! $user->can('inventory.items.manage')) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($unit->branch_id);
    }

    /**
     * Determine whether the user can delete the inventory unit.
     */
    public function delete(User $user, InventoryUnit $unit): bool
    {
        if (! $user->can('inventory.items.manage')) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($unit->branch_id);
    }
}

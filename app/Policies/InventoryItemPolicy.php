<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    /**
     * Determine whether the user can view any inventory items.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    /**
     * Determine whether the user can view the inventory item.
     */
    public function view(User $user, InventoryItem $item): bool
    {
        if (! $user->can('inventory.view')) {
            return false;
        }

        // Global admins can view any item
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($item->branch_id);
    }

    /**
     * Determine whether the user can create inventory items.
     */
    public function create(User $user): bool
    {
        return $user->can('inventory.items.manage');
    }

    /**
     * Determine whether the user can update the inventory item.
     */
    public function update(User $user, InventoryItem $item): bool
    {
        if (! $user->can('inventory.items.manage')) {
            return false;
        }

        // Global admins can update any item
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($item->branch_id);
    }

    /**
     * Determine whether the user can delete the inventory item.
     */
    public function delete(User $user, InventoryItem $item): bool
    {
        if (! $user->can('inventory.items.manage')) {
            return false;
        }

        // Global admins can delete any item
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($item->branch_id);
    }

    /**
     * Determine whether the user can receive stock.
     */
    public function receiveStock(User $user, InventoryItem $item): bool
    {
        if (! $user->can('inventory.stock.receive')) {
            return false;
        }

        // Global admins can receive to any branch
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($item->branch_id);
    }

    /**
     * Determine whether the user can adjust stock.
     */
    public function adjustStock(User $user, InventoryItem $item): bool
    {
        if (! $user->can('inventory.stock.adjust')) {
            return false;
        }

        // Global admins can adjust any
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($item->branch_id);
    }

    /**
     * Determine whether the user can issue stock.
     */
    public function issueStock(User $user, InventoryItem $item): bool
    {
        if (! $user->can('inventory.issue')) {
            return false;
        }

        // Global admins can issue from any branch
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($item->branch_id);
    }
}

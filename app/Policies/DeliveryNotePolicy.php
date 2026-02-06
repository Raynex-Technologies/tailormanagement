<?php

namespace App\Policies;

use App\Models\DeliveryNote;
use App\Models\User;

class DeliveryNotePolicy
{
    /**
     * Determine whether the user can view any delivery notes.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('delivery_notes.view');
    }

    /**
     * Determine whether the user can view the delivery note.
     */
    public function view(User $user, DeliveryNote $deliveryNote): bool
    {
        if (! $user->can('delivery_notes.view')) {
            return false;
        }

        // Global admins can view any delivery note
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($deliveryNote->branch_id);
    }

    /**
     * Determine whether the user can create delivery notes.
     */
    public function create(User $user): bool
    {
        return $user->can('delivery_notes.create');
    }

    /**
     * Determine whether the user can delete the delivery note.
     */
    public function delete(User $user, DeliveryNote $deliveryNote): bool
    {
        // Only global admins and branch managers can delete
        if (! $user->hasBranchAdminPowers()) {
            return false;
        }

        // Global admins can delete any
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch managers can only delete in their branch
        return $user->canAccessBranch($deliveryNote->branch_id);
    }
}

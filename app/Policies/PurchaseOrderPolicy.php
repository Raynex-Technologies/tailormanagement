<?php

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    /**
     * Determine whether the user can view any purchase orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.po.manage') || $user->can('procurement.receive');
    }

    /**
     * Determine whether the user can view the purchase order.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->can('procurement.po.manage') && ! $user->can('procurement.receive')) {
            return false;
        }

        // Global admins can view any
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Others can only view POs in their branch
        return $user->canAccessBranch($purchaseOrder->branch_id);
    }

    /**
     * Determine whether the user can create purchase orders.
     */
    public function create(User $user): bool
    {
        return $user->can('procurement.po.manage');
    }

    /**
     * Determine whether the user can manage (update/send) the purchase order.
     */
    public function manage(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->can('procurement.po.manage')) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($purchaseOrder->branch_id);
    }

    /**
     * Determine whether the user can mark as sent.
     */
    public function markSent(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->can('procurement.po.manage')) {
            return false;
        }

        // Can only mark sent if draft
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($purchaseOrder->branch_id);
    }

    /**
     * Determine whether the user can receive goods for this PO.
     */
    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->can('procurement.receive')) {
            return false;
        }

        // Can only receive if sent or partially received
        if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived])) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($purchaseOrder->branch_id);
    }

    /**
     * Determine whether the user can cancel the purchase order.
     */
    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->can('procurement.po.manage')) {
            return false;
        }

        // Can only cancel if draft or sent (not received)
        if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent])) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($purchaseOrder->branch_id);
    }
}

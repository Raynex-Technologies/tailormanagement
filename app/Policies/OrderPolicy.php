<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        if (! $user->can('orders.view')) {
            return false;
        }

        // Global admins can view any order
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check for non-global users
        if (! $user->canAccessBranch($order->branch_id)) {
            return false;
        }

        // Tailors can only view their assigned orders
        if ($user->hasRole('tailor')) {
            return $order->isAssignedToTailor($user->id);
        }

        return true;
    }

    /**
     * Determine whether the user can view order financial information.
     * This includes: total, subtotal, discount, paid, balance, payment status, unit prices, line totals.
     *
     * Storekeeper does NOT have this permission.
     * Admin, branch_manager, accountant DO have this permission.
     */
    public function viewFinancials(User $user, Order $order): bool
    {
        if (! $user->can('orders.view_financials')) {
            return false;
        }

        // Must also be able to view the order itself
        if (! $this->view($user, $order)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can view materials for this order.
     * This is primarily for storekeepers to see requested/issued inventory items.
     */
    public function viewMaterials(User $user, Order $order): bool
    {
        if (! $user->can('orders.materials.view')) {
            return false;
        }

        // Must also be able to view the order itself
        if (! $this->view($user, $order)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can manage materials for this order.
     * This allows creating stock requests and fulfilling them.
     * Primarily for storekeepers.
     */
    public function manageMaterials(User $user, Order $order): bool
    {
        if (! $user->can('orders.materials.manage')) {
            return false;
        }

        // Global admins can manage materials on any order
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($order->branch_id);
    }

    /**
     * Determine whether the user can record payments for this order.
     * Admin, branch_manager, and accountant can record payments.
     * Storekeeper cannot.
     */
    public function recordPayments(User $user, Order $order): bool
    {
        if (! $user->can('payments.create')) {
            return false;
        }

        // Must also be able to view the order
        if (! $this->view($user, $order)) {
            return false;
        }

        // Global admins can record payments on any order
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($order->branch_id);
    }

    /**
     * Determine whether the user can view payments for this order.
     * Admin, branch_manager, and accountant can view payments.
     * Storekeeper cannot.
     */
    public function viewPayments(User $user, Order $order): bool
    {
        if (! $user->can('payments.view')) {
            return false;
        }

        // Must also be able to view the order
        if (! $this->view($user, $order)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create orders.
     */
    public function create(User $user): bool
    {
        return $user->can('orders.create');
    }

    /**
     * Determine whether the user can update the order.
     * Note: This is for updating order details/pricing. Storekeeper cannot update orders.
     * Editing is disabled for delivered or completed orders.
     */
    public function update(User $user, Order $order): bool
    {
        if (! $user->can('orders.update')) {
            return false;
        }

        // Cannot edit orders that are already delivered or completed
        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Completed])) {
            return false;
        }

        // Global admins can update any order (except final statuses above)
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($order->branch_id);
    }

    /**
     * Determine whether the user can change the order status.
     */
    public function changeStatus(User $user, Order $order): bool
    {
        if (! $user->can('orders.change_status')) {
            return false;
        }

        // Global admins can change status on any order
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($order->branch_id);
    }

    /**
     * Determine whether the user can assign a tailor to the order.
     */
    public function assignTailor(User $user, Order $order): bool
    {
        if (! $user->can('orders.assign_tailor')) {
            return false;
        }

        // Global admins can assign on any order
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($order->branch_id);
    }

    /**
     * Determine whether the user can mark the order as completed.
     */
    public function markCompleted(User $user, Order $order): bool
    {
        if (! $user->can('orders.mark_completed')) {
            return false;
        }

        // Global admins can complete any order
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($order->branch_id);
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
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
        return $user->canAccessBranch($order->branch_id);
    }

    /**
     * Determine whether the user can create a delivery note for the order.
     */
    public function createDeliveryNote(User $user, Order $order): bool
    {
        if (! $user->can('delivery_notes.create')) {
            return false;
        }

        // Global admins can create for any order
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($order->branch_id);
    }
}

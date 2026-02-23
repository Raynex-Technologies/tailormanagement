<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStockRequest;
use App\Models\User;

class OrderStockRequestPolicy
{
    /**
     * Determine whether the user can view any stock requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('stock_requests.view');
    }

    /**
     * Determine whether the user can view the stock request.
     */
    public function view(User $user, OrderStockRequest $stockRequest): bool
    {
        if (! $user->can('stock_requests.view')) {
            return false;
        }

        // Global admins can view any request
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        if (! $user->canAccessBranch($stockRequest->branch_id)) {
            return false;
        }

        // Tailors can only view requests for their assigned orders
        if ($user->hasRole('tailor')) {
            return $stockRequest->order?->assigned_tailor_id === $user->id
                || $stockRequest->requested_by === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can create stock requests.
     * Cannot create new stock requests for delivered or completed orders.
     */
    public function create(User $user, Order $order): bool
    {
        if (! $user->can('stock_requests.create')) {
            return false;
        }

        // Cannot create stock requests for orders that are delivered or completed
        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Completed])) {
            return false;
        }

        // Global admins can create for any order (except final statuses above)
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        if (! $user->canAccessBranch($order->branch_id)) {
            return false;
        }

        // Tailors can only create for their assigned orders
        if ($user->hasRole('tailor')) {
            return $order->assigned_tailor_id === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can review (approve/decline) the stock request.
     */
    public function review(User $user, OrderStockRequest $stockRequest): bool
    {
        if (! $user->can('stock_requests.review')) {
            return false;
        }

        // Global admins can review any request
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        if (! $user->canAccessBranch($stockRequest->branch_id)) {
            return false;
        }

        // Request must be in reviewable state
        return $stockRequest->canBeReviewed();
    }

    /**
     * Determine whether the user can fulfill the stock request.
     */
    public function fulfill(User $user, OrderStockRequest $stockRequest): bool
    {
        if (! $user->can('stock_requests.fulfill')) {
            return false;
        }

        // Global admins can fulfill any request
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        if (! $user->canAccessBranch($stockRequest->branch_id)) {
            return false;
        }

        // Request must be in fulfillable state
        return $stockRequest->canBeFulfilled();
    }

    /**
     * Determine whether the user can delete the stock request.
     * Only allow deletion of pending requests by admins.
     */
    public function delete(User $user, OrderStockRequest $stockRequest): bool
    {
        // Only allow deletion of pending requests
        if (! $stockRequest->canBeReviewed()) {
            return false;
        }

        // Only global admins and branch managers can delete
        if (! $user->hasBranchAdminPowers()) {
            return false;
        }

        // Global admins can delete any
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch managers can only delete in their branch
        return $user->canAccessBranch($stockRequest->branch_id);
    }
}

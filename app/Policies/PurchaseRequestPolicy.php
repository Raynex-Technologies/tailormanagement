<?php

namespace App\Policies;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    /**
     * Determine whether the user can view any purchase requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    /**
     * Determine whether the user can view the purchase request.
     */
    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if (! $user->can('procurement.view')) {
            return false;
        }

        // Global admins can view any
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Others can only view PRs in their branch
        return $user->canAccessBranch($purchaseRequest->branch_id);
    }

    /**
     * Determine whether the user can create purchase requests.
     */
    public function create(User $user): bool
    {
        return $user->can('procurement.request.create');
    }

    /**
     * Determine whether the user can update the purchase request.
     */
    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if (! $user->can('procurement.request.create')) {
            return false;
        }

        // Can only update if draft and same branch
        if ($purchaseRequest->status !== PurchaseRequestStatus::Draft) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($purchaseRequest->branch_id);
    }

    /**
     * Determine whether the user can submit the purchase request.
     */
    public function submit(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if (! $user->can('procurement.request.submit')) {
            return false;
        }

        // Can only submit if draft
        if ($purchaseRequest->status !== PurchaseRequestStatus::Draft) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($purchaseRequest->branch_id);
    }

    /**
     * Determine whether the user can review (approve/decline) the purchase request.
     */
    public function review(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if (! $user->can('procurement.request.review')) {
            return false;
        }

        // Can only review if submitted
        if ($purchaseRequest->status !== PurchaseRequestStatus::Submitted) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($purchaseRequest->branch_id);
    }

    /**
     * Determine whether the user can approve the purchase request.
     */
    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if (! $user->can('procurement.request.approve')) {
            return false;
        }

        // Can only approve if submitted
        if ($purchaseRequest->status !== PurchaseRequestStatus::Submitted) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($purchaseRequest->branch_id);
    }

    /**
     * Determine whether the user can decline the purchase request.
     */
    public function decline(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if (! $user->can('procurement.request.decline')) {
            return false;
        }

        // Can only decline if submitted
        if ($purchaseRequest->status !== PurchaseRequestStatus::Submitted) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($purchaseRequest->branch_id);
    }

    /**
     * Determine whether the user can convert to PO.
     */
    public function convertToPo(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if (! $user->can('procurement.po.manage')) {
            return false;
        }

        // Can only convert if approved
        if ($purchaseRequest->status !== PurchaseRequestStatus::Approved) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($purchaseRequest->branch_id);
    }
}

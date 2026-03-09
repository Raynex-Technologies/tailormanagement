<?php

namespace App\Policies;

use App\Models\InstallmentPlan;
use App\Models\User;

class InstallmentPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('installments.view');
    }

    public function view(User $user, InstallmentPlan $plan): bool
    {
        if (! $user->can('installments.view')) {
            return false;
        }

        return $user->isGlobalAdmin() || $user->canAccessBranch($plan->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->can('installments.manage');
    }

    public function update(User $user, InstallmentPlan $plan): bool
    {
        if (! $user->can('installments.manage')) {
            return false;
        }

        return $user->isGlobalAdmin() || $user->canAccessBranch($plan->branch_id);
    }

    public function recordPayment(User $user, InstallmentPlan $plan): bool
    {
        if (! $user->can('installments.payments.record')) {
            return false;
        }

        return $user->isGlobalAdmin() || $user->canAccessBranch($plan->branch_id);
    }
}

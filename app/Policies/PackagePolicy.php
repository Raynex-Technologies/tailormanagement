<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('installments.view');
    }

    public function view(User $user, Package $package): bool
    {
        if (! $user->can('installments.view')) {
            return false;
        }

        return $user->isGlobalAdmin() || $user->canAccessBranch($package->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->can('installments.packages.manage');
    }

    public function update(User $user, Package $package): bool
    {
        if (! $user->can('installments.packages.manage')) {
            return false;
        }

        return $user->isGlobalAdmin() || $user->canAccessBranch($package->branch_id);
    }

    public function delete(User $user, Package $package): bool
    {
        return $this->update($user, $package);
    }
}

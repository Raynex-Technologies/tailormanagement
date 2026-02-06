<?php

namespace App\Models\Scopes;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip if context not initialized (e.g., during migrations, seeders, console)
        if (! BranchContext::isInitialized()) {
            return;
        }

        // Get the authenticated user
        $user = Auth::user();

        // No user = no restriction (commands, seeders, etc.)
        if (! $user) {
            return;
        }

        // Global admins (admin/superadmin) see all data unless they've selected a branch
        if ($user->isGlobalAdmin()) {
            $branchId = BranchContext::id();

            // If admin has selected a specific branch, filter by it
            if ($branchId !== null) {
                $builder->where($model->getTable() . '.branch_id', $branchId);
            }
            // Otherwise, no filtering - they see all branches

            return;
        }

        // All other users: strictly filter by their branch
        $branchId = BranchContext::id();

        if ($branchId !== null) {
            $builder->where($model->getTable() . '.branch_id', $branchId);
        } else {
            // Safety: if non-admin has no branch, return nothing
            $builder->whereRaw('1 = 0');
        }
    }
}

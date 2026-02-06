<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Support\BranchContext;
use DomainException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait for models that are scoped to a branch.
 *
 * Provides:
 * - Global scope to automatically filter by branch
 * - Auto-setting of branch_id on create with strict enforcement
 * - Branch relationship
 * - Protection against null branch_id
 */
trait BranchScoped
{
    /**
     * Boot the trait.
     */
    public static function bootBranchScoped(): void
    {
        // Add global scope for query filtering
        static::addGlobalScope(new BranchScope);

        // Auto-set and enforce branch_id on creating
        static::creating(function ($model) {
            $model->branch_id = static::determineBranchIdForCreate($model->branch_id);
        });

        // Protect branch_id from being changed by non-global admins
        static::updating(function ($model) {
            if ($model->isDirty('branch_id')) {
                $user = Auth::user();

                // Only global admins can change branch_id after creation
                if ($user && ! $user->isGlobalAdmin()) {
                    throw new DomainException(
                        'Branch assignment cannot be changed. Contact an administrator if you need to transfer this record.'
                    );
                }

                // Ensure new branch_id is not null
                if ($model->branch_id === null) {
                    throw new DomainException(
                        'Branch is required and cannot be set to null.'
                    );
                }
            }
        });
    }

    /**
     * Determine and enforce the branch_id to use when creating a new model.
     *
     * Rules:
     * - Branch-tied users: ALWAYS use their branch_id, ignore explicit input
     * - Global admins: Use explicit input, then context, then throw error
     *
     * @param  int|null  $explicitBranchId  Branch ID explicitly set on the model
     * @return int The enforced branch_id
     *
     * @throws DomainException If branch cannot be determined
     */
    protected static function determineBranchIdForCreate(?int $explicitBranchId): int
    {
        $user = Auth::user();

        // No user context - this shouldn't happen in normal flow
        if (! $user) {
            if ($explicitBranchId !== null) {
                return $explicitBranchId;
            }

            throw new DomainException(
                'Branch is required for this action. User authentication required.'
            );
        }

        // Branch-tied users: ALWAYS use their branch, ignore any explicit input
        if (! $user->isGlobalAdmin()) {
            if (! $user->branch_id) {
                throw new DomainException(
                    'Your account is not assigned to a branch. Please contact an administrator.'
                );
            }

            return $user->branch_id;
        }

        // Global admin: prefer explicit input from validated form
        if ($explicitBranchId !== null) {
            return $explicitBranchId;
        }

        // Then try BranchContext
        if (BranchContext::hasBranch()) {
            return BranchContext::id();
        }

        // Then try user's own branch (if global admin has one)
        if ($user->branch_id !== null) {
            return $user->branch_id;
        }

        // No branch available - error
        throw new DomainException(
            'Branch is required for this action. Please select a branch before proceeding.'
        );
    }

    /**
     * Get the branch that owns this model.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Scope to filter by a specific branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope to include all branches (bypass global scope).
     * Use with caution - only for admin operations.
     */
    public function scopeWithoutBranchScope($query)
    {
        return $query->withoutGlobalScope(BranchScope::class);
    }

    /**
     * Check if this model belongs to a specific branch.
     */
    public function belongsToBranch(int $branchId): bool
    {
        return $this->branch_id === $branchId;
    }

    /**
     * Check if this model belongs to the current user's branch.
     */
    public function belongsToUserBranch(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->branch_id) {
            return false;
        }

        return $this->branch_id === $user->branch_id;
    }
}

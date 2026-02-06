<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    /**
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.categories.manage');
    }

    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, ExpenseCategory $category): bool
    {
        if (! $user->can('expenses.categories.manage')) {
            return false;
        }

        // Global admins can view any
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($category->branch_id);
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->can('expenses.categories.manage');
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, ExpenseCategory $category): bool
    {
        if (! $user->can('expenses.categories.manage')) {
            return false;
        }

        // Global admins can update any
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($category->branch_id);
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, ExpenseCategory $category): bool
    {
        if (! $user->can('expenses.categories.manage')) {
            return false;
        }

        // Global admins can delete any
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->canAccessBranch($category->branch_id);
    }
}

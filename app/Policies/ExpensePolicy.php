<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Determine whether the user can view any expenses.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    /**
     * Determine whether the user can view the expense.
     */
    public function view(User $user, Expense $expense): bool
    {
        if (! $user->can('expenses.view')) {
            return false;
        }

        // Global admins can view any expense
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($expense->branch_id);
    }

    /**
     * Determine whether the user can create expenses.
     */
    public function create(User $user): bool
    {
        return $user->can('expenses.manage');
    }

    /**
     * Determine whether the user can update the expense.
     */
    public function update(User $user, Expense $expense): bool
    {
        if (! $user->can('expenses.manage')) {
            return false;
        }

        // Global admins can update any expense
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($expense->branch_id);
    }

    /**
     * Determine whether the user can delete the expense.
     */
    public function delete(User $user, Expense $expense): bool
    {
        if (! $user->can('expenses.manage')) {
            return false;
        }

        // Global admins can delete any expense
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch check
        return $user->canAccessBranch($expense->branch_id);
    }

    /**
     * Determine whether the user can manage expense categories.
     */
    public function manageCategories(User $user): bool
    {
        return $user->can('expenses.categories.manage');
    }
}

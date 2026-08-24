<?php

namespace App\Support\Customers;

use App\Models\Customer;
use App\Support\BranchContext;
use Illuminate\Support\Facades\Gate;

final class CustomerAccess
{
    public static function authorizeView(Customer $customer): void
    {
        Gate::authorize('users.view');
        self::enforceBranchBoundary($customer);
    }

    public static function authorizeManage(Customer $customer): void
    {
        Gate::authorize('users.manage');
        self::enforceBranchBoundary($customer);
    }

    private static function enforceBranchBoundary(Customer $customer): void
    {
        $actor = auth()->user();
        if (! $actor) {
            abort(403);
        }

        if (! $actor->isGlobalAdmin() && $customer->branch_id !== $actor->branch_id) {
            abort(404);
        }

        $activeBranchId = BranchContext::id();
        if ($actor->isGlobalAdmin() && $activeBranchId && $customer->branch_id !== $activeBranchId) {
            abort(404);
        }
    }
}

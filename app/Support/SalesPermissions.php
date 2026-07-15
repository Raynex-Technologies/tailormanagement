<?php

namespace App\Support;

use App\Models\PosSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class SalesPermissions
{
    public const VIEW_OWN = 'sales.view.own';

    public const VIEW_BRANCH = 'sales.view.branch';

    public const VIEW_ALL = 'sales.view.all';

    public static function canViewAny(User $user): bool
    {
        return self::canViewAll($user)
            || self::canViewBranch($user)
            || self::canViewOwn($user);
    }

    public static function canView(User $user, PosSale $sale): bool
    {
        if (self::canViewAll($user)) {
            return $user->isGlobalAdmin()
                || $user->branch_id === null
                || (int) $sale->branch_id === (int) $user->branch_id;
        }

        if (self::canViewBranch($user)) {
            return $user->branch_id !== null && (int) $sale->branch_id === (int) $user->branch_id;
        }

        if (self::canViewOwn($user)) {
            return (int) $sale->user_id === (int) $user->id;
        }

        return false;
    }

    public static function applyVisibility(Builder $query, User $user): Builder
    {
        if (self::canViewAll($user)) {
            if ($user->isGlobalAdmin() || $user->branch_id === null) {
                return $query;
            }

            return $query->where('branch_id', $user->branch_id);
        }

        if (self::canViewBranch($user)) {
            if ($user->branch_id === null) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('branch_id', $user->branch_id);
        }

        if (self::canViewOwn($user)) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canViewOwn(User $user): bool
    {
        return self::safeCan($user, self::VIEW_OWN);
    }

    public static function canViewBranch(User $user): bool
    {
        return self::safeCan($user, self::VIEW_BRANCH);
    }

    public static function canViewAll(User $user): bool
    {
        return self::safeCan($user, self::VIEW_ALL);
    }

    protected static function safeCan(User $user, string $permission): bool
    {
        try {
            return $user->can($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}

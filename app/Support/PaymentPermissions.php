<?php

namespace App\Support;

use App\Models\User;

class PaymentPermissions
{
    public const CREATE = 'payments.create';

    public const LEGACY_CREATE = 'payment.create';

    public const VIEW = 'payments.view';

    public const LEGACY_VIEW = 'payment.view';

    public static function canCreate(User $user): bool
    {
        return self::hasAnyPermission($user, [self::CREATE, self::LEGACY_CREATE]);
    }

    public static function canView(User $user): bool
    {
        return self::hasAnyPermission($user, [self::VIEW, self::LEGACY_VIEW]);
    }

    /**
     * Read assigned Spatie permission names directly so legacy aliases do not
     * depend on Gate registration or on unrelated permissions like view.
     *
     * @param  array<int, string>  $permissions
     */
    protected static function hasAnyPermission(User $user, array $permissions): bool
    {
        return $user->getAllPermissions()
            ->pluck('name')
            ->intersect($permissions)
            ->isNotEmpty();
    }
}

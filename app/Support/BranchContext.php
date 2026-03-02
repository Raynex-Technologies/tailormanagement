<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Singleton class to hold the current branch context.
 * Used by global scopes and policies to enforce branch boundaries.
 */
class BranchContext
{
    protected static ?int $branchId = null;

    protected static bool $initialized = false;

    /**
     * Set the current branch context.
     */
    public static function set(?int $branchId): void
    {
        self::$branchId = $branchId;
        self::$initialized = true;
    }

    /**
     * Get the current branch ID (nullable).
     */
    public static function id(): ?int
    {
        return self::$branchId;
    }

    /**
     * Get the current branch ID or throw if not set.
     * Use this for branch-required operations.
     *
     * @throws RuntimeException
     */
    public static function requireId(): int
    {
        if (self::$branchId === null) {
            throw new RuntimeException(
                'Branch context is required but not set. Please select a branch or ensure you are logged in with a branch assignment.'
            );
        }

        return self::$branchId;
    }

    /**
     * Get the effective branch ID for record creation.
     * For branch-tied users: returns their branch (required).
     * For global admins: returns context branch (may be null).
     *
     * @param  int|null  $explicitBranchId  Explicit branch ID from form input
     * @return int The branch ID to use
     *
     * @throws RuntimeException If branch is required but not available
     */
    public static function getEffectiveBranchId(?int $explicitBranchId = null): int
    {
        $user = Auth::user();

        if (! $user) {
            throw new RuntimeException('User must be authenticated to create branch-scoped records.');
        }

        // Branch-tied users: always use their branch, ignore any explicit input
        if (! $user->isGlobalAdmin()) {
            if (! $user->branch_id) {
                throw new RuntimeException(
                    'Your account is not assigned to a branch. Please contact an administrator.'
                );
            }

            return $user->branch_id;
        }

        // Global admin: prefer explicit input, then context, then user's branch
        if ($explicitBranchId !== null) {
            return $explicitBranchId;
        }

        if (self::$branchId !== null) {
            return self::$branchId;
        }

        if ($user->branch_id !== null) {
            return $user->branch_id;
        }

        throw new RuntimeException(
            'Branch is required for this action. Please select a branch before proceeding.'
        );
    }

    /**
     * Check if context has been initialized.
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * Check if a branch is currently set.
     */
    public static function hasBranch(): bool
    {
        return self::$branchId !== null;
    }

    /**
     * Check if the current user is a global admin and must select a branch.
     * Returns true if user is global admin and no branch context is set.
     */
    public static function mustSelectForGlobal(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Only applies to global admins
        if (! $user->isGlobalAdmin()) {
            return false;
        }

        // Must select if no context branch and no user branch
        return self::$branchId === null && $user->branch_id === null;
    }

    /**
     * Check if the current user needs to select a branch.
     * For global admins without a branch selected - returns true.
     * For branch-tied users - always returns false (they're auto-assigned).
     */
    public static function needsBranchSelection(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Only global admins can/need to select branches
        if (! $user->isGlobalAdmin()) {
            return false;
        }

        // Needs selection if no branch context is set
        return self::$branchId === null;
    }

    /**
     * Get the session key used for storing active branch.
     */
    public static function getSessionKey(): string
    {
        return 'active_branch_id';
    }

    /**
     * Check if the current user is a global admin.
     */
    public static function isGlobalAdmin(): bool
    {
        $user = Auth::user();

        return $user && $user->isGlobalAdmin();
    }

    /**
     * Check if the current user should see a branch selector in forms.
     * Global admins always see the selector.
     */
    public static function shouldShowBranchSelector(): bool
    {
        $user = Auth::user();

        return $user && $user->isGlobalAdmin();
    }

    /**
     * Get the current Branch model.
     */
    public static function branch(): ?Branch
    {
        if (self::$branchId === null) {
            return null;
        }

        return Branch::find(self::$branchId);
    }

    /**
     * Clear the branch context (for testing/reset).
     */
    public static function clear(): void
    {
        self::$branchId = null;
        self::$initialized = false;
    }

    /**
     * Initialize from the authenticated user.
     * Called by middleware.
     */
    public static function initializeFromUser(): void
    {
        $user = Auth::user();

        if (! $user) {
            self::set(null);

            return;
        }

        // Global admins can switch branches via session
        if ($user->isGlobalAdmin()) {
            $branchId = self::defaultGlobalAdminBranchId();

            self::set($branchId);

            if ($branchId !== null) {
                session([self::getSessionKey() => $branchId]);
            } else {
                session()->forget(self::getSessionKey());
            }

            return;
        }

        // All other users are bound to their assigned branch
        self::set($user->branch_id);
    }

    /**
     * Set active branch for admin users (stored in session).
     */
    public static function setActiveBranch(int $branchId): void
    {
        session(['active_branch_id' => $branchId]);
        self::set($branchId);
    }

    /**
     * Clear active branch selection for admin users.
     */
    public static function clearActiveBranch(): void
    {
        session()->forget(self::getSessionKey());

        $user = Auth::user();

        if ($user?->isGlobalAdmin()) {
            self::set(self::defaultGlobalAdminBranchId());

            return;
        }

        self::set($user?->branch_id);
    }

    /**
     * Resolve the active branch for global admins.
     * Keeps an explicit session selection when valid, otherwise defaults
     * to the first active branch in the database.
     */
    protected static function defaultGlobalAdminBranchId(): ?int
    {
        $sessionBranchId = session(self::getSessionKey());

        if ($sessionBranchId !== null) {
            $activeSessionBranchId = Branch::query()
                ->active()
                ->whereKey($sessionBranchId)
                ->value('id');

            if ($activeSessionBranchId !== null) {
                return (int) $activeSessionBranchId;
            }
        }

        $firstActiveBranchId = Branch::query()
            ->active()
            ->orderBy('id')
            ->value('id');

        return $firstActiveBranchId !== null ? (int) $firstActiveBranchId : null;
    }
}

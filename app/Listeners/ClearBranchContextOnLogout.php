<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

/**
 * Clear the active branch selection from session on logout.
 * This ensures users must re-select a branch on next login.
 */
class ClearBranchContextOnLogout
{
    /**
     * Handle the logout event.
     */
    public function handle(Logout $event): void
    {
        // Clear the active branch from session
        session()->forget('active_branch_id');
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetBranchContext
{
    /**
     * Handle an incoming request.
     * Sets the branch context based on the authenticated user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Initialize branch context from the authenticated user
        BranchContext::initializeFromUser();

        $user = $request->user();

        // If user is authenticated but not a global admin, ensure they have a branch
        if ($user && ! $user->isGlobalAdmin() && ! BranchContext::hasBranch()) {
            abort(403, 'Your account is not assigned to a branch. Please contact an administrator.');
        }

        return $next($request);
    }
}

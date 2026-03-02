@auth
    @if (auth()->user()->isGlobalAdmin() && !\App\Support\BranchContext::hasBranch())
        <div class="px-4 pt-4 lg:px-6 lg:pt-6">
            <div class="rounded-2xl p-4 border" style="background: linear-gradient(135deg, rgba(251, 191, 36, 0.1) 0%, rgba(245, 158, 11, 0.1) 100%); border-color: rgba(251, 191, 36, 0.3);">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center size-10 rounded-xl" style="background: rgba(251, 191, 36, 0.2);">
                            <i class="fa-duotone fa-triangle-exclamation size-5 text-amber-500"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-amber-700 dark:text-amber-300">
                            {{ __('No Active Branch Available') }}
                        </h3>
                        <p class="mt-1 text-sm text-amber-600/80 dark:text-amber-400/80">
                            {{ __('Branch-scoped pages need an active branch, but none is currently available.') }}
                        </p>
                        <p class="mt-2 text-sm font-medium text-amber-600 dark:text-amber-400">
                            {{ __('Create or activate a branch, then use the sidebar branch selector to switch to it.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endauth

@auth
    @if (auth()->user()->isGlobalAdmin() && !\App\Support\BranchContext::hasBranch())
        <div class="px-4 pt-4 lg:px-6 lg:pt-6">
            <div class="rounded-2xl p-4 border" style="background: linear-gradient(135deg, rgba(251, 191, 36, 0.1) 0%, rgba(245, 158, 11, 0.1) 100%); border-color: rgba(251, 191, 36, 0.3);">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center size-10 rounded-xl" style="background: rgba(251, 191, 36, 0.2);">
                            <svg class="size-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-amber-700 dark:text-amber-300">
                            {{ __('Branch Selection Required') }}
                        </h3>
                        <p class="mt-1 text-sm text-amber-600/80 dark:text-amber-400/80">
                            {{ __('As a global administrator, you need to select a branch from the sidebar to view branch-specific data and create records.') }}
                        </p>
                        <p class="mt-2 text-sm font-medium text-amber-600 dark:text-amber-400">
                            {{ __('Use the "Active Branch" dropdown in the sidebar to select a branch.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endauth

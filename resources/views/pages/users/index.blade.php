<x-layouts::app :title="__('Users')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-4 lg:p-6">
        {{-- Page Header --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">
                        {{ __('User Management') }}
                    </h1>
                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                        {{ __('Manage all users in your tailoring business system.') }}
                    </p>
                </div>
                <div>
                    <button
                        disabled
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white opacity-50 shadow-sm cursor-not-allowed"
                    >
                        <i class="fa-duotone fa-plus h-5 w-5"></i>
                        {{ __('Add User') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Coming Soon Placeholder --}}
        <div class="flex flex-1 items-center justify-center rounded-2xl border border-dashed border-zinc-300 bg-white p-12 dark:border-zinc-600 dark:bg-zinc-800/50">
            <div class="text-center">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/50">
                    <i class="fa-duotone fa-users h-10 w-10 text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <h2 class="mt-6 text-xl font-semibold text-zinc-900 dark:text-white">
                    {{ __('Coming in Phase 2') }}
                </h2>
                <p class="mt-2 max-w-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('User management features including creating, editing, and managing user accounts with role assignments will be available in the next phase.') }}
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                        <i class="fa-duotone fa-circle mr-1.5 h-3 w-3"></i>
                        Create Users
                    </span>
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                        <i class="fa-duotone fa-circle mr-1.5 h-3 w-3"></i>
                        Assign Roles
                    </span>
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                        <i class="fa-duotone fa-circle mr-1.5 h-3 w-3"></i>
                        User Profiles
                    </span>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>

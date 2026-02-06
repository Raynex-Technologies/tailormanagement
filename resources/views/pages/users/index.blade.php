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
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ __('Add User') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Coming Soon Placeholder --}}
        <div class="flex flex-1 items-center justify-center rounded-2xl border border-dashed border-zinc-300 bg-white p-12 dark:border-zinc-600 dark:bg-zinc-800/50">
            <div class="text-center">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/50">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="h-10 w-10 text-indigo-600 dark:text-indigo-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <h2 class="mt-6 text-xl font-semibold text-zinc-900 dark:text-white">
                    {{ __('Coming in Phase 2') }}
                </h2>
                <p class="mt-2 max-w-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('User management features including creating, editing, and managing user accounts with role assignments will be available in the next phase.') }}
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                        <svg class="mr-1.5 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                            <circle cx="4" cy="4" r="3" />
                        </svg>
                        Create Users
                    </span>
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                        <svg class="mr-1.5 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                            <circle cx="4" cy="4" r="3" />
                        </svg>
                        Assign Roles
                    </span>
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                        <svg class="mr-1.5 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                            <circle cx="4" cy="4" r="3" />
                        </svg>
                        User Profiles
                    </span>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>

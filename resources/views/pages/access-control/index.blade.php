<x-layouts::app :title="__('Roles & Permissions')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-4 lg:p-6">
        {{-- Page Header --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">
                        {{ __('Roles & Permissions') }}
                    </h1>
                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                        {{ __('Manage access control for your tailoring business system.') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        disabled
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-100 px-4 py-2.5 text-sm font-semibold text-zinc-700 opacity-50 shadow-sm cursor-not-allowed dark:bg-zinc-700 dark:text-zinc-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ __('Add Permission') }}
                    </button>
                    <button
                        disabled
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white opacity-50 shadow-sm cursor-not-allowed"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ __('Add Role') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Current Roles Overview --}}
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-700">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Current Roles') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Roles configured in the system') }}</p>
            </div>
            <div class="p-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $roles = \Spatie\Permission\Models\Role::withCount('users', 'permissions')->get();
                    @endphp
                    @foreach($roles as $role)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-700/50">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ $role->name === 'superadmin' ? 'bg-purple-100 dark:bg-purple-900/50' : 'bg-indigo-100 dark:bg-indigo-900/50' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 {{ $role->name === 'superadmin' ? 'text-purple-600 dark:text-purple-400' : 'text-indigo-600 dark:text-indigo-400' }}">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold capitalize text-zinc-900 dark:text-white">{{ $role->name }}</p>
                                    <div class="flex gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span>{{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}</span>
                                        <span>•</span>
                                        <span>{{ $role->permissions_count }} {{ Str::plural('permission', $role->permissions_count) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Current Permissions --}}
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-700">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Current Permissions') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Available permissions in the system') }}</p>
            </div>
            <div class="p-6">
                <div class="flex flex-wrap gap-2">
                    @php
                        $permissions = \Spatie\Permission\Models\Permission::all();
                    @endphp
                    @foreach($permissions as $permission)
                        <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-1.5 h-4 w-4 text-emerald-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                            </svg>
                            {{ $permission->name }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Coming Soon Features --}}
        <div class="flex items-center justify-center rounded-2xl border border-dashed border-zinc-300 bg-white p-8 dark:border-zinc-600 dark:bg-zinc-800/50">
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/50">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="h-8 w-8 text-amber-600 dark:text-amber-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.048.58.024 1.194-.14 1.743" />
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">
                    {{ __('Full Management Coming in Phase 2') }}
                </h2>
                <p class="mt-2 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Create custom roles, manage permissions, and configure granular access control for your entire team.') }}
                </p>
            </div>
        </div>
    </div>
</x-layouts::app>

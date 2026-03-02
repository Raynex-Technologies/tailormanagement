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
                        <i class="fa-duotone fa-plus h-5 w-5"></i>
                        {{ __('Add Permission') }}
                    </button>
                    <button
                        disabled
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white opacity-50 shadow-sm cursor-not-allowed"
                    >
                        <i class="fa-duotone fa-plus h-5 w-5"></i>
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
                                    <i class="fa-duotone fa-shield-halved h-5 w-5 {{ $role->name === 'superadmin' ? 'text-purple-600 dark:text-purple-400' : 'text-indigo-600 dark:text-indigo-400' }}"></i>
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
                            <i class="fa-duotone fa-key mr-1.5 h-4 w-4 text-emerald-500"></i>
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
                    <i class="fa-duotone fa-screwdriver-wrench h-8 w-8 text-amber-600 dark:text-amber-400"></i>
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

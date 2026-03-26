<div>
    <flux:main class="space-y-6">
        {{-- Breadcrumbs --}}
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
            <flux:breadcrumbs.item>{{ __('Roles & Permissions') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-circle">
                {{ session('error') }}
            </flux:callout>
        @endif

        {{-- Header --}}
        <flux:card>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="xl">{{ __('Roles & Permissions') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Manage roles and assign permissions by module.') }}</flux:text>
                </div>
                <flux:button variant="primary" :href="route('access-control.roles.create')" wire:navigate>
                    <x-icon name="add" class="mr-1 size-4" />
                    {{ __('Add Role') }}
                </flux:button>
            </div>
        </flux:card>

        {{-- Roles grid --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Current Roles') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($roles as $role)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-700/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $role->name === 'superadmin' ? 'bg-purple-100 dark:bg-purple-900/50' : 'bg-indigo-100 dark:bg-indigo-900/50' }}">
                                    <x-icon name="shield" class="size-5 {{ $role->name === 'superadmin' ? 'text-purple-600 dark:text-purple-400' : 'text-indigo-600 dark:text-indigo-400' }}" />
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold capitalize text-zinc-900 dark:text-white">{{ str_replace('_', ' ', $role->name) }}</p>
                                    <div class="flex gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span>{{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}</span>
                                        <span>â€¢</span>
                                        <span>{{ $role->permissions_count }} {{ Str::plural('permission', $role->permissions_count) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <flux:button size="xs" variant="ghost" :href="route('access-control.roles.edit', $role)" wire:navigate>
                                    <x-icon name="edit" class="size-4" />
                                </flux:button>
                                @if ($role->users_count > 0)
                                    <span
                                        title="{{ __('Cannot delete: this role has users assigned. Reassign users first.') }}"
                                        class="inline-flex cursor-not-allowed items-center justify-center rounded-lg p-2 opacity-50"
                                    >
                                        <x-icon name="delete" class="size-4 text-zinc-400" />
                                    </span>
                                @else
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        wire:click="deleteRole({{ $role->id }})"
                                        wire:confirm="Are you sure you want to delete the role &quot;{{ str_replace('_', ' ', $role->name) }}&quot;? This cannot be undone."
                                    >
                                        <x-icon name="delete" class="size-4 text-red-500" />
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        {{-- Permissions reference (grouped) --}}
        @php
            $allPermissions = \Spatie\Permission\Models\Permission::orderBy('name')->get();
            $grouped = \App\Support\PermissionGroups::groupPermissions($allPermissions);
        @endphp
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Permissions by Module') }}</flux:heading>
            <flux:text class="mb-4 block text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('These permissions can be assigned to roles when creating or editing a role.') }}
            </flux:text>
            <div class="space-y-6">
                @foreach ($grouped as $moduleLabel => $perms)
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ $moduleLabel }}</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($perms as $p)
                                <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                    {{ $p->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </flux:main>
</div>

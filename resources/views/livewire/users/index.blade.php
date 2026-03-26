<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Users') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Header --}}
    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Users') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Manage user accounts and role assignments.') }}</flux:text>
            </div>
            @if ($canManage)
                <flux:button variant="primary" :href="route('users.create')" wire:navigate>
                    <x-icon name="add" class="mr-1 size-4" />
                    {{ __('Add User') }}
                </flux:button>
            @endif
        </div>
    </flux:card>

    {{-- Stats Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Users') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($stats['total']) }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input
                wire:model.blur="search"
                placeholder="{{ __('Search by name or email...') }}"
                icon="magnifying-glass"
            />

            <flux:select wire:model.blur="roleFilter">
                <flux:select.option value="">{{ __('All Roles') }}</flux:select.option>
                @foreach ($roles as $role)
                    <flux:select.option value="{{ $role }}">{{ ucfirst(str_replace('_', ' ', $role)) }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($isGlobalAdmin && $branches->isNotEmpty())
                <flux:select wire:model.blur="branchFilter">
                    <flux:select.option value="">{{ __('All Branches') }}</flux:select.option>
                    @foreach ($branches as $branch)
                        <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="flex items-end">
                <flux:button wire:click="clearFilters" variant="ghost" size="sm">
                    <x-icon name="close" class="mr-1 size-4" />
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Users Table --}}
    <flux:card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">{{ __('Name') }}</th>
                        <th class="px-4 py-3">{{ __('Email') }}</th>
                        <th class="px-4 py-3">{{ __('Role') }}</th>
                        <th class="px-4 py-3">{{ __('Branch') }}</th>
                        <th class="px-4 py-3">{{ __('Created') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($users as $user)
                        <tr class="text-sm text-zinc-900 hover:bg-zinc-50 dark:text-zinc-100 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-xs font-semibold text-white">
                                        {{ $user->initials() }}
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $user->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                @foreach ($user->roles as $role)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        @if(in_array($role->name, ['superadmin', 'admin'])) bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400
                                        @elseif($role->name === 'branch_manager') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400
                                        @else bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300
                                        @endif">
                                        {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3">
                                {{ $user->branch?->name ?? 'â€”' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-zinc-500">
                                {{ $user->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="xs" variant="ghost" :href="route('users.show', $user)" wire:navigate>
                                        <x-icon name="visibility" class="size-4" />
                                    </flux:button>
                                    @if ($canManage && auth()->user()->can('update', $user))
                                        <flux:button size="xs" variant="ghost" :href="route('users.edit', $user)" wire:navigate>
                                            <x-icon name="edit" class="size-4" />
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No users found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                {{ $users->links() }}
            </div>
        @endif
    </flux:card>
</flux:main>

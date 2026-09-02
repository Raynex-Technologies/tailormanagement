<div x-data="{ filtersOpen: @js($search !== '' || $roleFilter !== '' || $branchFilter !== '') }">
    <style>
        [data-user-desktop-list] { display: block; }
        [data-user-mobile-list] { display: none; }

        @media (max-width: 767px) {
            [data-user-desktop-list] { display: none; }
            [data-user-mobile-list] { display: block; }
        }
    </style>

    <flux:main class="p-0">
        <section class="mb-6 overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:p-6" style="background: linear-gradient(135deg, var(--tm-hero) 0%, color-mix(in srgb, var(--tm-hero) 88%, #ffffff 12%) 100%);" data-theme-hero data-users-workspace-header>
            <flux:breadcrumbs class="mb-5 text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item class="!text-white">{{ __('Users') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <flux:heading size="xl" class="!text-white">{{ __('Users') }}</flux:heading>
                    <p class="mt-1 text-sm text-white/70">{{ __('Manage staff accounts, roles and system access.') }}</p>
                </div>
                @if ($canCreate)
                    <flux:button variant="primary" icon="plus" :href="route('users.create')" wire:navigate>{{ __('New User') }}</flux:button>
                @endif
            </div>
        </section>

        @if (session('success'))
            <flux:callout class="mb-4" variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <section class="mb-4" aria-labelledby="user-overview-heading">
            <div class="mb-3">
                <h2 id="user-overview-heading" class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('User overview') }}</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Non-sensitive account totals for your access scope.') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['total', __('Total Users'), 'fa-users', 'text-sky-600 bg-sky-50 dark:bg-sky-950/50 dark:text-sky-300'],
                    ['with_roles', __('Users with Roles'), 'fa-user-shield', 'text-violet-600 bg-violet-50 dark:bg-violet-950/50 dark:text-violet-300'],
                    ['without_roles', __('Users without Roles'), 'fa-user-slash', 'text-amber-600 bg-amber-50 dark:bg-amber-950/50 dark:text-amber-300'],
                    ['roles_in_use', __('Roles in Use'), 'fa-key', 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/50 dark:text-emerald-300'],
                ] as [$key, $label, $icon, $color])
                    <flux:card>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                                <p class="mt-2 text-2xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ number_format($stats[$key]) }}</p>
                            </div>
                            <span class="inline-flex size-10 items-center justify-center rounded-xl {{ $color }}"><i class="fa-duotone {{ $icon }}" aria-hidden="true"></i></span>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </section>

        <div class="mb-4 flex justify-end">
            <flux:button variant="ghost" icon="funnel" x-on:click="filtersOpen = ! filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="user-filters">{{ __('Filters') }}</flux:button>
        </div>

        <div id="user-filters" x-show="filtersOpen" x-collapse x-cloak class="mb-4" data-user-filter-panel>
            <flux:card>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name or email...') }}" icon="magnifying-glass" class="sm:col-span-2" />
                <flux:select wire:model.live="roleFilter" label="{{ __('Role') }}">
                    <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
                    @foreach ($roles as $role)<flux:select.option value="{{ $role }}">{{ str($role)->replace('_', ' ')->title() }}</flux:select.option>@endforeach
                </flux:select>
                @if ($isGlobalAdmin && $branches->isNotEmpty())
                    <flux:select wire:model.live="branchFilter" label="{{ __('Branch') }}">
                        <flux:select.option value="">{{ __('All branches') }}</flux:select.option>
                        @foreach ($branches as $branch)<flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>@endforeach
                    </flux:select>
                @endif
                <div class="flex items-end gap-2">
                    <flux:select wire:model.live="perPage" label="{{ __('Rows') }}" class="flex-1">
                        @foreach ([10, 15, 25, 50] as $size)<flux:select.option value="{{ $size }}">{{ $size }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters" class="mb-0.5">{{ __('Clear') }}</flux:button>
                </div>
                </div>
            </flux:card>
        </div>

        <flux:card data-user-list>
            <div class="overflow-x-auto" data-user-desktop-list>
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead><tr class="text-left text-sm font-semibold text-zinc-900 dark:text-white">
                        <th class="px-4 py-3">{{ __('User') }}</th><th class="px-4 py-3">{{ __('Role') }}</th><th class="px-4 py-3">{{ __('Access Scope') }}</th><th class="px-4 py-3">{{ __('Created') }}</th><th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($users as $user)
                            <tr class="text-sm text-zinc-700 dark:text-zinc-300" wire:key="user-{{ $user->id }}">
                                <td class="px-4 py-3"><div class="flex items-center gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">{{ $user->initials() }}</span>
                                    <div class="min-w-0"><a href="{{ route('users.show', $user) }}" wire:navigate class="font-semibold text-indigo-600 underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-400">{{ $user->name }}</a><p class="truncate text-xs text-zinc-500">{{ $user->email }}</p></div>
                                </div></td>
                                <td class="px-4 py-3">@forelse ($user->roles as $role)<span class="inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">{{ str($role->name)->replace('_', ' ')->title() }}</span>@empty<span class="text-zinc-500">{{ __('Unassigned') }}</span>@endforelse</td>
                                <td class="px-4 py-3">{{ $user->branch?->name ?? __('Global access') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-500">{{ $user->created_at->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-right">@can('update', $user)<flux:button size="sm" variant="ghost" :href="route('users.edit', $user)" wire:navigate>{{ __('Edit') }}</flux:button>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-12 text-center"><p class="font-semibold text-zinc-900 dark:text-white">{{ __('No users found') }}</p><p class="mt-1 text-sm text-zinc-500">{{ __('No users match the current search or filters.') }}</p>@if ($canCreate)<flux:button class="mt-4" size="sm" variant="primary" icon="plus" :href="route('users.create')" wire:navigate>{{ __('Create User') }}</flux:button>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700" data-user-mobile-list>
                @forelse ($users as $user)
                    <article class="py-4 first:pt-0 last:pb-0" wire:key="mobile-user-{{ $user->id }}">
                        <div class="flex items-start gap-3"><span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">{{ $user->initials() }}</span><div class="min-w-0 flex-1"><a href="{{ route('users.show', $user) }}" wire:navigate class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $user->name }}</a><p class="truncate text-sm text-zinc-500">{{ $user->email }}</p></div></div>
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-xs text-zinc-500">{{ __('Role') }}</dt><dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ str($user->roles->first()?->name ?? 'Unassigned')->replace('_', ' ')->title() }}</dd></div><div><dt class="text-xs text-zinc-500">{{ __('Access Scope') }}</dt><dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $user->branch?->name ?? __('Global') }}</dd></div></dl>
                        @can('update', $user)<div class="mt-3"><flux:button class="w-full" size="sm" variant="ghost" :href="route('users.edit', $user)" wire:navigate>{{ __('Edit User') }}</flux:button></div>@endcan
                    </article>
                @empty
                    <div class="py-10 text-center"><p class="font-semibold text-zinc-900 dark:text-white">{{ __('No users found') }}</p><p class="mt-1 text-sm text-zinc-500">{{ __('No users match the current search or filters.') }}</p></div>
                @endforelse
            </div>
            @if ($users->hasPages())<div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">{{ $users->links() }}</div>@endif
        </flux:card>
    </flux:main>
</div>

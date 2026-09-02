<div>
    <flux:main class="space-y-8">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
            <flux:breadcrumbs.item>{{ __('Roles & Permissions') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-circle">{{ session('error') }}</flux:callout>
        @endif

        <flux:card data-theme-hero style="background: linear-gradient(135deg, var(--tm-hero) 0%, color-mix(in srgb, var(--tm-hero) 88%, #ffffff 12%) 100%);">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <flux:heading size="xl">{{ __('Roles & Permissions') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Manage staff roles and control access to system features.') }}</flux:text>
                </div>
                <flux:button variant="primary" icon="plus" :href="route('access-control.roles.create')" wire:navigate class="w-full justify-center sm:w-auto">
                    {{ __('Add Role') }}
                </flux:button>
            </div>
        </flux:card>

        <section aria-labelledby="current-roles-heading" data-role-grid>
            <div class="mb-4">
                <flux:heading id="current-roles-heading" size="lg">{{ __('Current Roles') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ trans_choice(':count role configured|:count roles configured', $roles->count(), ['count' => $roles->count()]) }}
                </flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($roles as $role)
                    @php($protected = in_array($role->name, $protectedRoles, true))
                    <article class="flex min-w-0 flex-col rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5" data-role-card>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a
                                    href="{{ route('access-control.roles.edit', $role) }}"
                                    wire:navigate
                                    class="block truncate text-base font-semibold capitalize text-zinc-950 underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--tm-accent)] dark:text-white"
                                >
                                    {{ str_replace('_', ' ', $role->name) }}
                                </a>
                                @if ($protected)
                                    <flux:badge size="sm" class="mt-2">{{ __('Protected') }}</flux:badge>
                                @endif
                            </div>
                        </div>

                        <dl class="mt-6 grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-zinc-200/80 p-3 dark:border-white/10">
                                <dd class="text-xl font-semibold text-zinc-950 dark:text-white">{{ $role->users_count }}</dd>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Users') }}</dt>
                            </div>
                            <div class="rounded-xl border border-zinc-200/80 p-3 dark:border-white/10">
                                <dd class="text-xl font-semibold text-zinc-950 dark:text-white">{{ $role->permissions_count }}</dd>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Permissions') }}</dt>
                            </div>
                        </dl>

                        <div class="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-zinc-200/80 pt-4 dark:border-white/10">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('access-control.roles.edit', $role)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                            @if (! $protected && $role->users_count === 0)
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="deleteRole({{ $role->id }})"
                                    wire:confirm="{{ __('Delete this role? This action cannot be undone.') }}"
                                >
                                    {{ __('Delete') }}
                                </flux:button>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center sm:col-span-2 xl:col-span-3 dark:border-white/15">
                        <flux:heading size="lg">{{ __('No roles yet') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Create a role to begin assigning system access.') }}</flux:text>
                    </div>
                @endforelse
            </div>
        </section>
    </flux:main>
</div>

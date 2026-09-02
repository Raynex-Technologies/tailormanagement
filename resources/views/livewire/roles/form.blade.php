<div>
    <flux:main class="space-y-8">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
            <flux:breadcrumbs.item href="{{ route('access-control.roles.index') }}" wire:navigate>{{ __('Roles & Permissions') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $isEdit ? __('Edit Role') : __('New Role') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-circle">{{ session('error') }}</flux:callout>
        @endif

        <flux:card data-theme-hero style="background: linear-gradient(135deg, var(--tm-hero) 0%, color-mix(in srgb, var(--tm-hero) 88%, #ffffff 12%) 100%);">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <flux:heading size="xl">{{ $isEdit ? __('Edit Role') : __('New Role') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $isEdit ? __('Update this staff role and its system access.') : __('Create a staff role and define its system access.') }}
                    </flux:text>
                </div>
                @if ($isEdit)
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <flux:badge>{{ str_replace('_', ' ', $name) }}</flux:badge>
                        <span class="text-white/75">{{ trans_choice(':count permission selected|:count permissions selected', $this->selectedPermissionsCount, ['count' => $this->selectedPermissionsCount]) }}</span>
                    </div>
                @endif
            </div>
        </flux:card>

        <form wire:submit="save" class="space-y-8" x-data="{ permissionSearch: '' }">
            <section aria-labelledby="role-information-heading" class="border-b border-zinc-200 pb-8 dark:border-white/10">
                <div class="mb-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Role Information') }}</p>
                    <flux:heading id="role-information-heading" size="lg" class="mt-1">{{ __('Role details') }}</flux:heading>
                </div>

                <div class="max-w-xl">
                    <flux:input
                        id="name"
                        wire:model="name"
                        label="{{ __('Role Name') }}"
                        placeholder="{{ __('e.g. receptionist') }}"
                        description="{{ __('Use lowercase letters, numbers, dashes, and underscores only.') }}"
                        required
                    />
                    @error('name')
                        <flux:text class="mt-1 text-sm">{{ $message }}</flux:text>
                    @enderror
                </div>
            </section>

            <section aria-labelledby="permissions-heading" data-permission-editor>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Permissions') }}</p>
                        <flux:heading id="permissions-heading" size="lg" class="mt-1">{{ __('System access') }}</flux:heading>
                        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Choose only the access this role needs.') }}
                        </flux:text>
                    </div>
                    <div class="w-full lg:w-80">
                        <flux:input
                            type="search"
                            x-model="permissionSearch"
                            icon="magnifying-glass"
                            placeholder="{{ __('Search permissions...') }}"
                            aria-label="{{ __('Search permissions') }}"
                        />
                    </div>
                </div>

                <div class="mt-4 text-sm font-medium text-zinc-700 dark:text-zinc-300" aria-live="polite">
                    {{ trans_choice(':count permission selected|:count permissions selected', $this->selectedPermissionsCount, ['count' => $this->selectedPermissionsCount]) }}
                </div>

                <div class="mt-6 grid items-start gap-5 lg:grid-cols-2">
                    @foreach ($groupedPermissions as $moduleLabel => $modulePermissions)
                        @if ($modulePermissions->isEmpty())
                            @continue
                        @endif
                        @php
                            $selectedInGroup = $modulePermissions->filter(fn ($permission) => ! empty($this->permissions[$permission->id]))->count();
                            $searchTerms = strtolower($moduleLabel.' '.$modulePermissions->map(fn ($permission) => \App\Support\PermissionGroups::displayName($permission->name))->implode(' '));
                        @endphp
                        <fieldset
                            class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5"
                            data-permission-group="{{ $moduleLabel }}"
                            data-search="{{ $searchTerms }}"
                            x-show="!permissionSearch.trim() || $el.dataset.search.includes(permissionSearch.toLowerCase().trim())"
                        >
                            <legend class="sr-only">{{ $moduleLabel }}</legend>
                            <div class="flex flex-col gap-3 border-b border-zinc-200 pb-4 sm:flex-row sm:items-start sm:justify-between dark:border-white/10">
                                <div>
                                    <h3 class="font-semibold text-zinc-950 dark:text-white">{{ $moduleLabel }}</h3>
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __(':selected of :total selected', ['selected' => $selectedInGroup, 'total' => $modulePermissions->count()]) }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-1">
                                    <flux:button type="button" size="xs" variant="ghost" wire:click="selectModule('{{ addslashes($moduleLabel) }}')">
                                        {{ __('Select All') }}
                                    </flux:button>
                                    <flux:button type="button" size="xs" variant="ghost" wire:click="clearModule('{{ addslashes($moduleLabel) }}')">
                                        {{ __('Clear') }}
                                    </flux:button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                @foreach ($modulePermissions as $permission)
                                    @if ($permission->name !== 'dashboard.view' && str_starts_with($permission->name, 'dashboard.') && empty($this->permissions[$dashboardViewPermissionId]))
                                        @continue
                                    @endif
                                    <label
                                        class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl px-3 py-2.5 text-sm transition hover:bg-zinc-100 focus-within:ring-2 focus-within:ring-[var(--tm-accent)] dark:hover:bg-white/5"
                                        data-search="{{ strtolower($moduleLabel.' '.\App\Support\PermissionGroups::displayName($permission->name)) }}"
                                        x-show="!permissionSearch.trim() || $el.dataset.search.includes(permissionSearch.toLowerCase().trim())"
                                    >
                                        <input
                                            type="checkbox"
                                            wire:model.live="permissions.{{ $permission->id }}"
                                            value="1"
                                            class="mt-0.5 size-4 shrink-0 rounded border-zinc-300 text-[var(--tm-accent)] focus:ring-[var(--tm-accent)] dark:border-zinc-600 dark:bg-zinc-800"
                                        />
                                        <span class="min-w-0 leading-5 text-zinc-700 dark:text-zinc-200">{{ \App\Support\PermissionGroups::displayName($permission->name) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 border-t border-zinc-200 pt-6 sm:flex-row sm:justify-end dark:border-white/10">
                <flux:button type="button" variant="ghost" :href="route('access-control.roles.index')" wire:navigate class="w-full justify-center sm:w-auto">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" icon="check" class="w-full justify-center sm:w-auto">
                    {{ $isEdit ? __('Save Changes') : __('Create Role') }}
                </flux:button>
            </div>
        </form>
    </flux:main>
</div>

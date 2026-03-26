<div>
    <flux:main class="space-y-6">
        {{-- Breadcrumbs --}}
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
            <flux:breadcrumbs.item href="{{ route('access-control.roles.index') }}" wire:navigate>{{ __('Roles & Permissions') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $isEdit ? __('Edit Role') : __('New Role') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-circle">
                {{ session('error') }}
            </flux:callout>
        @endif

        <form wire:submit="save">
            <flux:card class="mb-6">
                <flux:heading size="xl" class="mb-6">
                    {{ $isEdit ? __('Edit Role') : __('New Role') }}
                </flux:heading>

                <div class="max-w-md">
                    <flux:label for="name">{{ __('Role name') }} *</flux:label>
                    <flux:input
                        id="name"
                        wire:model="name"
                        placeholder="e.g. manager, receptionist"
                    />
                    <flux:text class="mt-1 text-xs text-zinc-500">
                        {{ __('Use lowercase letters, numbers, and underscores only.') }}
                    </flux:text>
                    @error('name')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
            </flux:card>

            {{-- Permissions by module --}}
            <flux:card class="mb-6">
                <flux:heading size="lg" class="mb-2">{{ __('Permissions') }}</flux:heading>
                <flux:text class="mb-6 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Select the permissions this role should have. You can tick a module header to select or clear all permissions in that module.') }}
                </flux:text>

                <div class="space-y-8">
                    @foreach ($groupedPermissions as $moduleLabel => $permissions)
                        @if ($permissions->isEmpty())
                            @continue
                        @endif
                        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div
                                class="flex cursor-pointer items-center justify-between border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50"
                                wire:click="toggleModule('{{ addslashes($moduleLabel) }}')"
                            >
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $moduleLabel }}</span>
                                <x-icon name="expand_more" class="size-4 text-zinc-500" />
                            </div>
                            <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($permissions as $permission)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="permissions.{{ $permission->id }}"
                                            value="1"
                                            class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-700"
                                        />
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" :href="route('access-control.roles.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    <x-icon name="check" class="mr-1 size-4" />
                    {{ $isEdit ? __('Update Role') : __('Create Role') }}
                </flux:button>
            </div>
        </form>
    </flux:main>
</div>

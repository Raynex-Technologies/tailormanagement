<flux:main class="space-y-6 p-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Administration') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Branches') }}</flux:breadcrumbs.item>
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

    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Branches') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                    {{ __('Manage business branches, review branch activity, and control which branches stay active.') }}
                </flux:text>
            </div>

            @if ($canManage)
                <flux:button variant="primary" wire:click="openCreateModal">
                    <x-icon name="add" class="mr-1 size-4" />
                    {{ __('New Branch') }}
                </flux:button>
            @endif
        </div>
    </flux:card>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Total Branches') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ number_format($stats['total']) }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Active') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ number_format($stats['active']) }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Inactive') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ number_format($stats['inactive']) }}</flux:heading>
        </flux:card>
    </div>

    <flux:card>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search branch name, code, phone, or address...') }}"
                icon="magnifying-glass"
            />

            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="perPage">
                <flux:select.option value="15">15 {{ __('per page') }}</flux:select.option>
                <flux:select.option value="25">25 {{ __('per page') }}</flux:select.option>
                <flux:select.option value="50">50 {{ __('per page') }}</flux:select.option>
            </flux:select>

            <div class="flex items-end">
                <flux:button type="button" wire:click="clearFilters" variant="ghost" size="sm">
                    <x-icon name="close" class="mr-1 size-4" />
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    <flux:card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">{{ __('Branch') }}</th>
                        <th class="px-4 py-3">{{ __('Contact') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3">{{ __('Users') }}</th>
                        <th class="px-4 py-3">{{ __('Orders') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($branches as $branch)
                        <tr class="text-sm text-zinc-900 hover:bg-zinc-50 dark:text-zinc-100 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $branch->name }}</div>
                                <div class="mt-1 text-xs font-mono text-zinc-500 dark:text-zinc-400">{{ $branch->code }}</div>
                                @if ($branch->address)
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $branch->address }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                {{ $branch->phone ?: __('No phone set') }}
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge color="{{ $branch->is_active ? 'green' : 'zinc' }}" size="sm">
                                    {{ $branch->is_active ? __('Active') : __('Inactive') }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge color="zinc" size="sm">{{ $branch->users_count }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge color="{{ $branch->orders_count > 0 ? 'blue' : 'zinc' }}" size="sm">{{ $branch->orders_count }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($canManage)
                                        <flux:button size="xs" variant="ghost" wire:click="openEditModal({{ $branch->id }})">
                                            <x-icon name="edit" class="size-4" />
                                        </flux:button>
                                    @endif

                                    @if ($canDelete && $branch->is_active)
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            wire:click="openDeletePasswordModal({{ $branch->id }})"
                                            class="text-red-600 hover:text-red-700 dark:text-red-400"
                                        >
                                            <x-icon name="delete" class="size-4" />
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No branches found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($branches->hasPages())
            <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                {{ $branches->links() }}
            </div>
        @endif
    </flux:card>

    <flux:modal wire:model="showFormModal" class="max-w-2xl">
        <div class="space-y-5">
            <flux:heading size="lg">
                {{ $isEditing ? __('Edit Branch') : __('New Branch') }}
            </flux:heading>

            <form wire:submit="save" class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <flux:label for="name">{{ __('Branch Name') }} *</flux:label>
                        <flux:input id="name" wire:model="name" placeholder="{{ __('Branch name') }}" />
                        @error('name')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div>
                        <flux:label for="code">{{ __('Code') }}{{ $isEditing ? ' *' : '' }}</flux:label>
                        <flux:input id="code" wire:model="code" placeholder="{{ __('Auto-generated if left blank') }}" />
                        @error('code')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div>
                        <flux:label for="phone">{{ __('Phone') }}</flux:label>
                        <flux:input id="phone" wire:model="phone" placeholder="{{ __('Phone number') }}" />
                        @error('phone')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <flux:heading size="sm">{{ __('Active Branch') }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Inactive branches are hidden from the branch switcher and create flows.') }}
                                </flux:text>
                            </div>
                            <flux:switch wire:model="is_active" />
                        </div>
                        @error('is_active')
                            <flux:text class="mt-2 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <flux:label for="address">{{ __('Address') }}</flux:label>
                        <flux:textarea id="address" wire:model="address" rows="3" />
                        @error('address')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="closeFormModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">
                            {{ $isEditing ? __('Update Branch') : __('Create Branch') }}
                        </span>
                        <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeletePasswordModal" class="max-w-lg">
        <div
            class="space-y-6"
            wire:loading.class="pointer-events-none opacity-60"
            wire:target="verifyDeletePassword"
        >
            <div class="flex items-start gap-4">
                <div class="flex size-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <x-icon name="warning" class="size-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">{{ __('Delete Branch') }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                        {{ __('Only a superadmin can continue, and password confirmation is required before the final warning step.') }}
                    </flux:text>
                </div>
            </div>

            <flux:text>
                {{ __('Enter your current password to continue deleting') }} <strong>{{ $deletingName }}</strong>.
            </flux:text>

            <div>
                <flux:label for="deletePassword">{{ __('Current Password') }}</flux:label>
                <flux:input id="deletePassword" type="password" wire:model="deletePassword" autocomplete="current-password" />
                @error('deletePassword')
                    <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeDeletePasswordModal" wire:loading.attr="disabled" wire:target="verifyDeletePassword">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="button" variant="danger" wire:click="verifyDeletePassword" wire:loading.attr="disabled" wire:target="verifyDeletePassword">
                    <span wire:loading.remove wire:target="verifyDeletePassword">{{ __('Continue') }}</span>
                    <span wire:loading wire:target="verifyDeletePassword">{{ __('Checking...') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeleteConfirmModal" class="max-w-2xl">
        <div
            class="space-y-6"
            wire:loading.class="pointer-events-none opacity-60"
            wire:target="deleteBranch"
        >
            <div class="flex items-start gap-4">
                <div class="flex size-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <x-icon name="warning" class="size-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">{{ __('Final Confirmation') }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                        {{ __('This action is intended to be irreversible from the application. Once you continue, the branch is retired from active use immediately.') }}
                    </flux:text>
                </div>
            </div>

            <flux:callout variant="danger" icon="exclamation-circle">
                {{ __('Delete') }} <strong>{{ $deletingName }}</strong>{{ __(' only if you are certain. There is no recovery flow in the application after this step.') }}
            </flux:callout>

            @if ($deleteImpact !== [])
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-900/20">
                    <flux:heading size="sm">{{ __('Data that can be soft-deleted now') }}</flux:heading>
                    <ul class="mt-3 space-y-2 text-sm text-amber-900 dark:text-amber-200">
                        @foreach ($deleteImpact as $label => $count)
                            <li>{{ $count }} {{ $label }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/60">
                <flux:heading size="sm">{{ __('Schema safety check') }}</flux:heading>
                @if ($deleteBlockers === [])
                    <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('No unsupported branch-owned records were found. The branch can be archived safely.') }}
                    </flux:text>
                @else
                    <flux:text class="mt-2 text-sm text-red-600 dark:text-red-400">
                        {{ __('Deletion cannot continue until these records are moved or cleared because they do not support soft deletion in the current schema:') }}
                    </flux:text>
                    <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-200">
                        @foreach ($deleteBlockers as $label => $count)
                            <li>{{ $count }} {{ $label }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/60">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400" wire:loading.remove wire:target="deleteBranch">
                    {{ __('All inputs stay locked while deletion is running. The operation only finishes when every step returns successfully or an error is raised.') }}
                </flux:text>
                <div class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-300" wire:loading wire:target="deleteBranch">
                    <i class="fa-duotone fa-spinner-third animate-spin"></i>
                    {{ __('Deleting branch data, please wait...') }}
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeDeleteConfirmModal" wire:loading.attr="disabled" wire:target="deleteBranch">
                    {{ __('Cancel') }}
                </flux:button>

                @if ($deleteBlockers === [])
                    <flux:button type="button" variant="danger" wire:click="deleteBranch" wire:loading.attr="disabled" wire:target="deleteBranch">
                        <span wire:loading.remove wire:target="deleteBranch">{{ __('Delete Branch') }}</span>
                        <span wire:loading wire:target="deleteBranch">{{ __('Deleting...') }}</span>
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</flux:main>

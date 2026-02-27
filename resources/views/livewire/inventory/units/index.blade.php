<div>
    <flux:main class="p-6">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Inventory Units') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                    {{ __('Manage unit options used when creating inventory items.') }}
                </flux:text>
            </div>

            @can('inventory.items.manage')
                <flux:button icon="plus" wire:click="openCreateModal">
                    {{ __('New Unit') }}
                </flux:button>
            @endcan
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                {{ session('error') }}
            </div>
        @endif

        <flux:card class="mb-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <flux:input
                        wire:model.blur="search"
                        placeholder="Search units..."
                        icon="magnifying-glass"
                    />
                </div>

                <flux:select wire:model.blur="perPage" class="w-32">
                    <flux:select.option value="15">15 per page</flux:select.option>
                    <flux:select.option value="25">25 per page</flux:select.option>
                    <flux:select.option value="50">50 per page</flux:select.option>
                </flux:select>
            </div>
        </flux:card>

        <flux:card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-sm font-semibold text-zinc-900 dark:text-white">
                            <th class="px-4 py-3">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Items') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($units as $unit)
                            <tr class="text-sm text-zinc-700 dark:text-zinc-300" wire:key="unit-{{ $unit->id }}">
                                <td class="px-4 py-3 font-medium">
                                    {{ $unit->name }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <flux:badge size="sm" color="{{ $unit->items_count > 0 ? 'blue' : 'zinc' }}">
                                        {{ $unit->items_count }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('inventory.items.manage')
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="pencil"
                                                wire:click="openEditModal({{ $unit->id }})"
                                            />
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="trash"
                                                wire:click="confirmDelete({{ $unit->id }})"
                                                class="text-red-600 hover:text-red-700 dark:text-red-500"
                                            />
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <x-icon name="inventory_2" class="size-12 text-zinc-300 dark:text-zinc-600" />
                                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                                            {{ __('No units found.') }}
                                        </flux:text>
                                        @can('inventory.items.manage')
                                            <flux:button size="sm" wire:click="openCreateModal">
                                                {{ __('Create your first unit') }}
                                            </flux:button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($units->hasPages())
                <div class="mt-4 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $units->links() }}
                </div>
            @endif
        </flux:card>
    </flux:main>

    <flux:modal wire:model="showModal" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $isEditing ? __('Edit Unit') : __('New Unit') }}
            </flux:heading>

            <form wire:submit="save" class="space-y-4">
                @if ($showBranchSelector && ! $isEditing)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                        <flux:label for="branchId">{{ __('Branch') }} *</flux:label>
                        <flux:select id="branchId" wire:model="branchId">
                            <flux:select.option value="">{{ __('-- Select Branch --') }}</flux:select.option>
                            @foreach ($branches as $branch)
                                <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                            {{ __('Select a branch to create this unit.') }}
                        </p>
                        @error('branchId')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <flux:input
                    wire:model.blur="name"
                    label="{{ __('Unit Name') }}"
                    placeholder="e.g., pcs, meters, sets"
                    required
                />
                @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="flex justify-end gap-3 pt-4">
                    <flux:button type="button" variant="ghost" wire:click="closeModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit">
                        {{ $isEditing ? __('Update') : __('Create') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="flex size-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <x-icon name="warning" class="size-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">{{ __('Delete Unit') }}</flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('This action cannot be undone.') }}
                    </flux:text>
                </div>
            </div>

            <flux:text>
                {{ __('Are you sure you want to delete') }} <strong>{{ $deletingName }}</strong>?
            </flux:text>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeDeleteModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="delete">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

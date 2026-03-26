<div>
    <flux:main class="space-y-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
            <flux:breadcrumbs.item :href="route('installments.dashboard')" wire:navigate>{{ __('Installments') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Packages') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Installment Packages') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Define credit packages and the garments included in each offer.') }}</flux:text>
            </div>

            <flux:button variant="primary" wire:click="openCreateModal">
                {{ __('New Package') }}
            </flux:button>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
                {{ session('success') }}
            </div>
        @endif

        <flux:card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search packages...') }}" />
                <flux:select wire:model.live="statusFilter">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </flux:select>
                <flux:select wire:model.live="perPage">
                    <option value="15">15 {{ __('per page') }}</option>
                    <option value="25">25 {{ __('per page') }}</option>
                    <option value="50">50 {{ __('per page') }}</option>
                </flux:select>
            </div>
        </flux:card>

        <div class="overflow-hidden rounded-2xl border border-zinc-200/50 bg-white shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">{{ __('Package') }}</th>
                            <th class="px-4 py-3">{{ __('Price') }}</th>
                            <th class="px-4 py-3">{{ __('Duration') }}</th>
                            <th class="px-4 py-3">{{ __('Items') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($packages as $package)
                            <tr class="text-sm text-zinc-900 dark:text-zinc-100">
                                <td class="px-4 py-4">
                                    <div class="font-semibold">{{ $package->name }}</div>
                                    @if($package->description)
                                        <div class="mt-1 max-w-md text-xs text-zinc-500 dark:text-zinc-400">{{ $package->description }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">{{ money_tzs($package->price) }}</td>
                                <td class="px-4 py-4">{{ $package->durationLabel() }}</td>
                                <td class="px-4 py-4">{{ number_format($package->items->count()) }}</td>
                                <td class="px-4 py-4">
                                    <button
                                        wire:click="toggleActive({{ $package->id }})"
                                        class="relative inline-flex h-6 w-11 rounded-full {{ $package->is_active ? 'bg-green-500' : 'bg-zinc-300 dark:bg-zinc-600' }}"
                                        type="button"
                                    >
                                        <span class="inline-block size-5 rounded-full bg-white shadow transition {{ $package->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                    </button>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="openCreateItemModal({{ $package->id }})">
                                            {{ __('Add Item') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="openEditModal({{ $package->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="bg-zinc-50/80 dark:bg-zinc-900/30">
                                <td colspan="6" class="px-4 py-4">
                                    <div class="flex flex-wrap gap-3">
                                        @forelse($package->items as $item)
                                            <div class="flex min-w-[240px] items-start gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-3 dark:border-zinc-700 dark:bg-zinc-800">
                                                @if($item->image_url)
                                                    <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="size-12 rounded-lg object-cover">
                                                @else
                                                    <div class="flex size-12 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                                                        <i class="fa-duotone fa-shirt text-zinc-400"></i>
                                                    </div>
                                                @endif
                                                <div class="min-w-0 flex-1">
                                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $item->name }}</div>
                                                    @if($item->description)
                                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $item->description }}</div>
                                                    @endif
                                                </div>
                                                <div class="flex gap-1">
                                                    <flux:button size="sm" variant="ghost" wire:click="openEditItemModal({{ $item->id }})">
                                                        <i class="fa-duotone fa-pen size-4"></i>
                                                    </flux:button>
                                                    <flux:button size="sm" variant="ghost" wire:click="deleteItem({{ $item->id }})">
                                                        <i class="fa-duotone fa-trash size-4 text-red-500"></i>
                                                    </flux:button>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No garments added to this package yet.') }}</div>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-16 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('No packages found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($packages->hasPages())
                <div class="border-t border-zinc-100 px-5 py-3 dark:border-zinc-700/50">
                    {{ $packages->links() }}
                </div>
            @endif
        </div>
    </flux:main>

    <flux:modal wire:model="showPackageModal" class="max-w-xl">
        <div class="space-y-5">
            <flux:heading size="lg">{{ $isEditing ? __('Edit Package') : __('New Package') }}</flux:heading>

            <form wire:submit="savePackage" class="space-y-4">
                @if($showBranchSelector && ! $isEditing)
                    <flux:select wire:model="branch_id" label="{{ __('Branch') }}">
                        <option value="">{{ __('Select branch') }}</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </flux:select>
                    @error('branch_id') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <flux:input wire:model="name" label="{{ __('Package Name') }}" required />
                        @error('name') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="price" type="number" step="0.01" label="{{ __('Price') }}" required />
                        @error('price') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <flux:input wire:model="duration_value" type="number" min="1" label="{{ __('Duration Value') }}" required />
                        @error('duration_value') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select wire:model="duration_unit" label="{{ __('Duration Unit') }}">
                            <option value="days">{{ __('Days') }}</option>
                            <option value="weeks">{{ __('Weeks') }}</option>
                            <option value="months">{{ __('Months') }}</option>
                            <option value="years">{{ __('Years') }}</option>
                        </flux:select>
                        @error('duration_unit') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <flux:textarea wire:model="description" label="{{ __('Description') }}" rows="3" />
                    @error('description') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <flux:checkbox wire:model="is_active" />
                    {{ __('Package is active') }}
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="closePackageModal">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ $isEditing ? __('Update') : __('Create') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal wire:model="showItemModal" class="max-w-xl">
        <div class="space-y-5">
            <flux:heading size="lg">{{ $editingItemId ? __('Edit Package Item') : __('New Package Item') }}</flux:heading>

            <form wire:submit="saveItem" class="space-y-4">
                <div>
                    <flux:input wire:model="itemName" label="{{ __('Item Name') }}" required />
                    @error('itemName') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <flux:textarea wire:model="itemDescription" label="{{ __('Description') }}" rows="3" />
                    @error('itemDescription') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <flux:input wire:model="itemSortOrder" type="number" min="0" label="{{ __('Sort Order') }}" />
                        @error('itemSortOrder') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Optional Image') }}</label>
                        <input type="file" wire:model="itemImageUpload" class="block w-full text-sm text-zinc-600 dark:text-zinc-300">
                        @error('itemImageUpload') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if($existingItemImagePath)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                        <p class="mb-2 text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Current image') }}</p>
                        <img src="{{ Storage::disk('public')->url($existingItemImagePath) }}" alt="" class="h-20 rounded-lg object-cover">
                    </div>
                @endif

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="closeItemModal">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save Item') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>

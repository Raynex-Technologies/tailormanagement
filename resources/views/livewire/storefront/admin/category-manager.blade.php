<div>
    <flux:main class="p-0">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Storefront') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Product Categories') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Storefront Product Categories') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Manage categories shown in storefront product browsing and filtering.') }}
                </flux:text>
            </div>

            @can('storefront.catalog.manage')
                <flux:button variant="primary" wire:click="openCreateModal">
                    <x-icon name="add" class="mr-1 size-4" />
                    {{ __('New Category') }}
                </flux:button>
            @endcan
        </div>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if (session('error'))
            <flux:callout class="mt-4" variant="danger" icon="x-circle">
                {{ session('error') }}
            </flux:callout>
        @endif

        <flux:card class="mt-6">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="w-full sm:w-80">
                    <flux:input wire:model.blur="search" placeholder="{{ __('Search categories') }}" />
                </div>
                <div class="w-full sm:w-36">
                    <flux:select wire:model.blur="perPage">
                        <flux:select.option value="15">15</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">{{ __('Category') }}</th>
                            @if ($showBranchSelector)
                                <th class="px-4 py-3">{{ __('Branch') }}</th>
                            @endif
                            <th class="px-4 py-3">{{ __('Slug') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Items') }}</th>
                            <th class="px-4 py-3">{{ __('Flags') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($categories as $category)
                            <tr class="text-sm">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if ($category->storefront_image_path)
                                            <img src="{{ asset('storage/'.$category->storefront_image_path) }}" alt="{{ $category->name }}" class="h-10 w-10 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                                        @endif
                                        <div>
                                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $category->name }}</p>
                                            @if ($category->description)
                                                <p class="text-xs text-zinc-500">{{ $category->description }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                @if ($showBranchSelector)
                                    <td class="px-4 py-3 text-zinc-500">{{ $category->branch?->name ?: '-' }}</td>
                                @endif
                                <td class="px-4 py-3 text-zinc-500">{{ $category->slug }}</td>
                                <td class="px-4 py-3 text-center">{{ $category->items_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-1">
                                        <flux:badge size="sm" :color="$category->storefront_is_visible ? 'green' : 'zinc'">
                                            {{ $category->storefront_is_visible ? __('Visible') : __('Hidden') }}
                                        </flux:badge>
                                        @if ($category->storefront_featured)
                                            <flux:badge size="sm" color="blue">{{ __('Featured') }}</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="openEditModal({{ $category->id }})">{{ __('Edit') }}</flux:button>
                                        <flux:button size="sm" variant="ghost" class="text-red-600" wire:click="confirmDelete({{ $category->id }})">{{ __('Delete') }}</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showBranchSelector ? 6 : 5 }}" class="px-4 py-10 text-center text-sm text-zinc-500">
                                    {{ __('No storefront categories found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $categories->links() }}
            </div>
        </flux:card>
    </flux:main>

    <flux:modal wire:model="showFormModal" wire:key="storefront-category-form-modal" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading size="lg">
                {{ $isEditing ? __('Edit Product Category') : __('New Product Category') }}
            </flux:heading>

            @if ($showBranchSelector && ! $isEditing)
                <flux:select wire:model.blur="branchId" label="{{ __('Branch') }}">
                    <flux:select.option value="">{{ __('Select branch') }}</flux:select.option>
                    @foreach ($branches as $branch)
                        <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model.blur="name" label="{{ __('Name') }}" required />
            <flux:input wire:model.blur="slug" label="{{ __('Slug') }}" />
            <flux:textarea wire:model.blur="description" label="{{ __('Description') }}" rows="3" />

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <span>{{ __('Visible on Storefront') }}</span>
                    <input type="checkbox" wire:model="storefrontVisible" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
                <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <span>{{ __('Featured Category') }}</span>
                    <input type="checkbox" wire:model="storefrontFeatured" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                </label>
            </div>

            <div class="space-y-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:label>{{ __('Category Image') }}</flux:label>
                @if ($existingImagePath && ! $imageUpload)
                    <img src="{{ asset('storage/'.$existingImagePath) }}" alt="{{ __('Category image') }}" class="h-20 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                @endif
                @if ($imageUpload)
                    <img src="{{ $imageUpload->temporaryUrl() }}" alt="{{ __('Preview') }}" class="h-20 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                @endif
                <input type="file" wire:model="imageUpload" accept="image/*" class="block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeFormModal">{{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="primary" wire:click="save">
                    {{ $isEditing ? __('Update') : __('Create') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" wire:key="storefront-category-delete-modal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete Category') }}</flux:heading>
            <flux:text>{{ __('Are you sure you want to delete') }} <strong>{{ $deletingName }}</strong>?</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeDeleteModal">{{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

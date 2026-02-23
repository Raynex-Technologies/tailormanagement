<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('expenses.index') }}" wire:navigate>{{ __('Expenses') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Categories') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
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
                <flux:heading size="xl">{{ __('Expense Categories') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Manage expense categories for organizing expenses.') }}</flux:text>
            </div>
            <flux:button wire:click="openCreateModal">
                <x-icon name="add" class="mr-1 size-4" />
                {{ __('New Category') }}
            </flux:button>
        </div>
    </flux:card>

    {{-- Search --}}
    <flux:card>
        <div class="w-full md:w-1/3">
            <flux:input wire:model.blur="search" placeholder="Search categories..." icon="magnifying-glass" />
        </div>
    </flux:card>

    {{-- Categories Table --}}
    <flux:card>
        @if ($categories->isEmpty())
            <div class="py-12 text-center">
                <x-icon name="sell" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-4">{{ __('No categories found') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Create a new category to get started.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Expenses') }}</flux:table.column>
                    <flux:table.column>{{ __('Created') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($categories as $category)
                        <flux:table.row wire:key="cat-{{ $category->id }}">
                            <flux:table.cell class="font-medium">
                                {{ $category->name }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">
                                    {{ $category->expenses_count }} {{ __('expenses') }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">
                                {{ $category->created_at->format('M d, Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:button size="xs" variant="ghost" wire:click="openEditModal({{ $category->id }})">
                                        <x-icon name="edit" class="size-4" />
                                    </flux:button>
                                    @if ($category->expenses_count === 0)
                                        <flux:button size="xs" variant="ghost" wire:click="delete({{ $category->id }})" wire:confirm="Are you sure you want to delete this category?">
                                            <x-icon name="delete" class="size-4 text-red-500" />
                                        </flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $categories->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Create/Edit Modal --}}
    <flux:modal wire:model="showFormModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit Category') : __('New Category') }}
            </flux:heading>

            <form wire:submit="save">
                <div>
                    <flux:label for="name">{{ __('Category Name') }} *</flux:label>
                    <flux:input id="name" wire:model="name" placeholder="Enter category name..." />
                    @error('name')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="$set('showFormModal', false)">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ $editingId ? __('Update') : __('Create') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</flux:main>

<flux:main class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Garment Options') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <flux:card>
        <div>
            <flux:heading size="xl">{{ __('Garment Options') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Manage dynamic garment customizations shown in the public booking wizard.') }}</flux:text>
        </div>
    </flux:card>

    <div class="grid gap-6 xl:grid-cols-3">
        <flux:card>
            <flux:heading size="lg">{{ $groupId ? __('Edit Group') : __('New Option Group') }}</flux:heading>
            <form wire:submit="saveGroup" class="mt-4 space-y-4">
                <flux:select wire:model.live="categoryId">
                    @foreach ($categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="groupName" placeholder="{{ __('Collar type, Sleeve type...') }}" />
                <flux:select wire:model="inputType">
                    @foreach (['select', 'multi_select', 'radio', 'checkbox', 'text', 'textarea', 'color', 'number'] as $type)
                        <flux:select.option value="{{ $type }}">{{ str($type)->replace('_', ' ')->headline() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="number" min="0" wire:model="sortOrder" placeholder="{{ __('Sort order') }}" />
                <div class="flex gap-4">
                    <flux:checkbox wire:model="isRequired" label="{{ __('Required') }}" />
                    <flux:checkbox wire:model="isActive" label="{{ __('Active') }}" />
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="resetGroup">{{ __('Reset') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save Group') }}</flux:button>
                </div>
            </form>
        </flux:card>

        <flux:card class="xl:col-span-2">
            <div class="space-y-4">
                @forelse ($groups as $group)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="font-semibold">{{ $group->name }}</div>
                                <div class="text-sm text-zinc-500">{{ str($group->input_type)->replace('_', ' ')->headline() }} · {{ $group->is_required ? __('Required') : __('Optional') }}</div>
                            </div>
                            <flux:button size="xs" variant="ghost" wire:click="editGroup({{ $group->id }})">{{ __('Edit') }}</flux:button>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($group->options as $option)
                                <button type="button" wire:click="toggleOption({{ $option->id }})" class="rounded-full border px-3 py-1 text-xs {{ $option->is_active ? 'border-lime-300 bg-lime-50 text-lime-800 dark:border-lime-800 dark:bg-lime-950/30 dark:text-lime-200' : 'border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800' }}">
                                    {{ $option->label }}
                                </button>
                            @endforeach
                        </div>
                        <div class="mt-3 flex gap-2">
                            <flux:input wire:model="optionLabel" placeholder="{{ __('New option label') }}" />
                            <flux:button type="button" wire:click="addOption({{ $group->id }})">{{ __('Add') }}</flux:button>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-zinc-500">{{ __('No option groups for this category yet.') }}</div>
                @endforelse
            </div>
        </flux:card>
    </div>
</flux:main>

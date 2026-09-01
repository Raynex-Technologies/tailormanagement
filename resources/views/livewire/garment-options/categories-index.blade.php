<flux:main class="space-y-6">
    <x-orders.workspace-header :title="__('Garment Categories')" :subtitle="__('Manage the garments customers and staff can customize.')">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs class="text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item :href="route('order-catalog.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Order Catalog') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item class="!text-white">{{ __('Garment Categories') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="outline" icon="ellipsis-vertical" class="!border-white/25 !bg-white/10 !text-white hover:!bg-white/20" aria-label="{{ __('Garment category actions') }}" />
                <flux:menu class="w-64">
                    <flux:menu.item :href="route('admin.garment-options.index')" wire:navigate icon="swatch">{{ __('Customization Choices') }}</flux:menu.item>
                    <flux:menu.item :href="route('admin.garment-option-groups.index')" wire:navigate icon="adjustments-horizontal">{{ __('Customization Sections') }}</flux:menu.item>
                    @can('garment-options.manage')
                        <flux:menu.separator />
                        <flux:menu.item wire:click="create" icon="plus">{{ __('New Category') }}</flux:menu.item>
                    @endcan
                </flux:menu>
            </flux:dropdown>
        </x-slot:actions>
    </x-orders.workspace-header>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <flux:card>
        <div class="grid gap-3 sm:grid-cols-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search garment categories') }}" />
            <flux:select wire:model.live="status">
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Archived') }}</flux:select.option>
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($categories as $category)
            <article wire:key="garment-category-{{ $category->id }}" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#1e1f2e]">
                <div class="aspect-[16/9] bg-zinc-100 dark:bg-white/5">
                    @if ($category->image_url)
                        <img src="{{ $category->image_url }}" alt="{{ $category->image_alt ?: $category->name }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-zinc-400"><i class="fa-duotone fa-shirt text-4xl"></i></div>
                    @endif
                </div>
                <div class="space-y-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ $category->name }}</h2>
                            <p class="mt-1 line-clamp-2 text-sm text-zinc-500">{{ $category->description ?: __('No description') }}</p>
                        </div>
                        <flux:badge size="sm" color="{{ $category->is_active ? 'lime' : 'zinc' }}">{{ $category->is_active ? __('Active') : __('Archived') }}</flux:badge>
                    </div>
                    <div class="grid grid-cols-2 gap-3 rounded-xl bg-zinc-50 p-3 text-sm dark:bg-white/5">
                        <div><span class="block text-xs text-zinc-500">{{ __('Sections') }}</span><strong>{{ $category->option_groups_count }}</strong></div>
                        <div><span class="block text-xs text-zinc-500">{{ __('Fabrics') }}</span><strong>{{ $category->fabrics_count }}</strong></div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <flux:button size="xs" variant="ghost" wire:click="view({{ $category->id }})">{{ __('View') }}</flux:button>
                        @can('garment-options.manage')
                            <flux:button size="xs" variant="ghost" wire:click="edit({{ $category->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="xs" variant="ghost" wire:click="toggleActive({{ $category->id }})">{{ $category->is_active ? __('Archive') : __('Activate') }}</flux:button>
                        @endcan
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 py-16 text-center text-zinc-500">{{ __('No garment categories match these filters.') }}</div>
        @endforelse
    </div>

    @if ($panelOpen)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-6" role="dialog" aria-modal="true">
            <div class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl sm:max-w-2xl sm:rounded-3xl dark:bg-[#1e1f2e]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $readOnly ? __('View Garment Category') : ($categoryId ? __('Edit Garment Category') : __('New Garment Category')) }}</flux:heading>
                        <flux:text class="mt-1 text-zinc-500">{{ __('The illustration is shown in the customer booking experience.') }}</flux:text>
                    </div>
                    <flux:button wire:click="closePanel" variant="ghost" icon="x-mark" />
                </div>
                <form wire:submit="save" class="mt-6 space-y-4">
                    <flux:input wire:model="name" label="{{ __('Category name') }}" placeholder="{{ __('e.g. Suit') }}" :disabled="$readOnly" />
                    <flux:textarea wire:model="description" label="{{ __('Description') }}" rows="3" :disabled="$readOnly" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="genderScope" label="{{ __('Audience') }}" :disabled="$readOnly">
                            <flux:select.option value="">{{ __('Not specified') }}</flux:select.option>
                            <flux:select.option value="male">{{ __('Men') }}</flux:select.option>
                            <flux:select.option value="female">{{ __('Women') }}</flux:select.option>
                            <flux:select.option value="unisex">{{ __('Unisex') }}</flux:select.option>
                            <flux:select.option value="children">{{ __('Children') }}</flux:select.option>
                        </flux:select>
                        <flux:input wire:model="sortOrder" type="number" min="0" label="{{ __('Sort order') }}" :disabled="$readOnly" />
                    </div>
                    @if (! $readOnly)
                        <flux:input wire:model="imageUpload" type="file" accept="image/jpeg,image/png,image/webp" label="{{ __('Illustration') }}" />
                        @if ($existingImagePath)
                            <flux:checkbox wire:model="removeImage" label="{{ __('Remove current illustration') }}" />
                        @endif
                    @endif
                    <flux:checkbox wire:model="isActive" label="{{ __('Active') }}" :disabled="$readOnly" />
                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 dark:border-white/10">
                        <flux:button type="button" wire:click="closePanel" variant="ghost">{{ $readOnly ? __('Close') : __('Cancel') }}</flux:button>
                        @if ($readOnly)
                            @can('garment-options.manage')<flux:button type="button" wire:click="edit({{ $categoryId }})" variant="primary">{{ __('Edit') }}</flux:button>@endcan
                        @else
                            <flux:button type="submit" variant="primary">{{ $categoryId ? __('Save Changes') : __('Create Category') }}</flux:button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif
</flux:main>

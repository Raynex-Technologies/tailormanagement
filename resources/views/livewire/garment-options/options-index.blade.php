<flux:main class="space-y-6">
    <x-orders.workspace-header :title="__('Garment Customizations')" :subtitle="__('Manage the choices shown when creating catalogue items and customer bookings.')">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs class="text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item :href="route('order-catalog.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Order Catalog') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item class="!text-white">{{ __('Garment Customizations') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="outline" icon="ellipsis-vertical" class="!border-white/25 !bg-white/10 !text-white hover:!bg-white/20" aria-label="{{ __('Garment customization actions') }}" />
                <flux:menu class="w-64">
                    <flux:menu.item :href="route('admin.garment-categories.index')" wire:navigate icon="squares-2x2">{{ __('Garment Categories') }}</flux:menu.item>
                    <flux:menu.item :href="route('admin.garment-option-groups.index')" wire:navigate icon="adjustments-horizontal">{{ __('Customization Sections') }}</flux:menu.item>
                    @can('garment-options.manage')
                        <flux:menu.separator />
                        <flux:menu.item wire:click="create" icon="plus">{{ __('New Choice') }}</flux:menu.item>
                    @endcan
                </flux:menu>
            </flux:dropdown>
        </x-slot:actions>
    </x-orders.workspace-header>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <flux:card>
        <div class="grid gap-3 md:grid-cols-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search options') }}" />
            <flux:select wire:model.live="categoryId" aria-label="{{ __('Filter by garment category') }}">
                <flux:select.option value="">{{ __('All garment categories') }}</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="groupFilter" aria-label="{{ __('Filter by customization section') }}">
                <flux:select.option value="">{{ __('All customization sections') }}</flux:select.option>
                @foreach ($groups as $group)
                    <flux:select.option value="{{ $group->id }}">{{ $group->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="status" aria-label="{{ __('Filter by status') }}">
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Archived') }}</flux:select.option>
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#1e1f2e]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-white/10">
                <thead class="bg-zinc-50 dark:bg-white/5">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        <th class="px-5 py-3">{{ __('Choice') }}</th>
                        <th class="px-5 py-3">{{ __('Garment') }}</th>
                        <th class="px-5 py-3">{{ __('Customization section') }}</th>
                        <th class="px-5 py-3">{{ __('Adjustment') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-white/10">
                    @forelse ($options as $option)
                        <tr wire:key="garment-option-{{ $option->id }}" class="text-sm text-zinc-700 hover:bg-zinc-50/70 dark:text-zinc-200 dark:hover:bg-white/5">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-zinc-900 dark:text-white">{{ $option->label }}</div>
                                <div class="mt-1 max-w-sm truncate text-xs text-zinc-500">{{ $option->description ?: __('No description') }}</div>
                            </td>
                            <td class="px-5 py-4">{{ $option->group?->garmentCategory?->name ?: __('Shared') }}</td>
                            <td class="px-5 py-4">{{ $option->group?->name ?: __('Unassigned') }}</td>
                            <td class="px-5 py-4">{{ $option->price_adjustment ? money_currency($option->price_adjustment, config('app.currency', 'TZS')) : '—' }}</td>
                            <td class="px-5 py-4">
                                <flux:badge size="sm" color="{{ $option->is_active ? 'lime' : 'zinc' }}">{{ $option->is_active ? __('Active') : __('Archived') }}</flux:badge>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="xs" variant="ghost" wire:click="view({{ $option->id }})">{{ __('View') }}</flux:button>
                                    @can('garment-options.manage')
                                        <flux:button size="xs" variant="ghost" wire:click="edit({{ $option->id }})">{{ __('Edit') }}</flux:button>
                                        <flux:button size="xs" variant="ghost" wire:click="toggleActive({{ $option->id }})">
                                            {{ $option->is_active ? __('Archive') : __('Activate') }}
                                        </flux:button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center text-sm text-zinc-500">{{ __('No garment options match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($panelOpen)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true">
            <div class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl sm:max-w-2xl sm:rounded-3xl dark:bg-[#1e1f2e]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $readOnly ? __('View Choice') : ($optionId ? __('Edit Choice') : __('New Choice')) }}</flux:heading>
                        <flux:text class="mt-1 text-zinc-500">{{ __('This option is shared by catalogue creation and public booking.') }}</flux:text>
                    </div>
                    <flux:button wire:click="closePanel" variant="ghost" icon="x-mark" aria-label="{{ __('Close') }}" />
                </div>

                <form wire:submit="save" class="mt-6 space-y-4">
                    <flux:select wire:model="groupId" label="{{ __('Customization section') }}" :disabled="$readOnly">
                        <flux:select.option value="">{{ __('Choose a customization section') }}</flux:select.option>
                        @foreach ($groups as $group)
                            <flux:select.option value="{{ $group->id }}">{{ $group->garmentCategory?->name }} — {{ $group->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="label" label="{{ __('Choice name') }}" placeholder="{{ __('e.g. Mandarin collar') }}" :disabled="$readOnly" />
                    <flux:textarea wire:model="description" label="{{ __('Description') }}" rows="3" :disabled="$readOnly" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="priceAdjustment" type="number" min="0" step="0.01" label="{{ __('Price adjustment') }}" :disabled="$readOnly" />
                        <flux:input wire:model="sortOrder" type="number" min="0" label="{{ __('Sort order') }}" :disabled="$readOnly" />
                    </div>
                    <flux:checkbox wire:model="isActive" label="{{ __('Active') }}" :disabled="$readOnly" />

                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 dark:border-white/10">
                        <flux:button type="button" wire:click="closePanel" variant="ghost">{{ $readOnly ? __('Close') : __('Cancel') }}</flux:button>
                        @if ($readOnly)
                            @can('garment-options.manage')
                                <flux:button type="button" wire:click="edit({{ $optionId }})" variant="primary">{{ __('Edit') }}</flux:button>
                            @endcan
                        @else
                            <flux:button type="submit" variant="primary">{{ $optionId ? __('Save Changes') : __('Create Choice') }}</flux:button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif
</flux:main>

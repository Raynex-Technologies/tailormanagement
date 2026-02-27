<div>
    <flux:main class="p-6">
        {{-- Page Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Inventory Items') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                    {{ __('Manage your inventory items, receive and adjust stock.') }}
                </flux:text>
            </div>

            @can('inventory.items.manage')
                <flux:button icon="plus" wire:click="openCreateModal">
                    {{ __('New Item') }}
                </flux:button>
            @endcan
        </div>

        {{-- Flash Messages --}}
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

        {{-- Filters --}}
        <flux:card class="mb-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input
                    wire:model.blur.debounce.300ms="search"
                    placeholder="Search by name or SKU..."
                    icon="magnifying-glass"
                />

                <flux:select wire:model.blur="categoryFilter">
                    <flux:select.option value="">All Categories</flux:select.option>
                    @foreach ($categories as $id => $name)
                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.blur="statusFilter">
                    <flux:select.option value="">All Status</flux:select.option>
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                    <flux:select.option value="low">Low Stock</flux:select.option>
                </flux:select>

                <flux:select wire:model.blur="perPage">
                    <flux:select.option value="15">15 per page</flux:select.option>
                    <flux:select.option value="25">25 per page</flux:select.option>
                    <flux:select.option value="50">50 per page</flux:select.option>
                </flux:select>
            </div>
        </flux:card>

        {{-- Items Table --}}
        <flux:card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-sm font-semibold text-zinc-900 dark:text-white">
                            <th class="px-4 py-3">{{ __('SKU') }}</th>
                            <th class="px-4 py-3">{{ __('Name') }}</th>
                            <th class="px-4 py-3">{{ __('Category') }}</th>
                            <th class="px-4 py-3">{{ __('Unit') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('On Hand') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($items as $item)
                            @php
                                $stock = $item->stock;
                                $onHand = $stock?->qty_on_hand ?? 0;
                                $isLow = $onHand <= $item->reorder_level;
                            @endphp
                            <tr class="text-sm text-zinc-700 dark:text-zinc-300" wire:key="item-{{ $item->id }}">
                                <td class="px-4 py-3">
                                    <code class="rounded bg-zinc-100 px-2 py-1 text-xs font-medium dark:bg-zinc-700">
                                        {{ $item->sku }}
                                    </code>
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    {{ $item->name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $item->category?->name ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $item->inventoryUnit?->name ?? $item->unit }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="{{ $isLow ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                        {{ number_format($onHand, 0) }}
                                    </span>
                                    @if ($isLow)
                                        <flux:badge size="sm" color="red" class="ml-1">Low</flux:badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @can('inventory.items.manage')
                                        <button
                                            wire:click="toggleActive({{ $item->id }})"
                                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 {{ $item->is_active ? 'bg-green-500' : 'bg-zinc-300 dark:bg-zinc-600' }}"
                                        >
                                            <span class="sr-only">Toggle active</span>
                                            <span
                                                class="pointer-events-none inline-block size-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $item->is_active ? 'translate-x-5' : 'translate-x-0' }}"
                                            ></span>
                                        </button>
                                    @else
                                        <flux:badge size="sm" color="{{ $item->is_active ? 'green' : 'zinc' }}">
                                            {{ $item->is_active ? 'Active' : 'Inactive' }}
                                        </flux:badge>
                                    @endcan
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('inventory.stock.receive')
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="arrow-down-tray"
                                                wire:click="openReceiveModal({{ $item->id }})"
                                                title="Receive Stock"
                                            />
                                        @endcan

                                        @can('inventory.stock.adjust')
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="adjustments-horizontal"
                                                wire:click="openAdjustModal({{ $item->id }})"
                                                title="Adjust Stock"
                                            />
                                        @endcan

                                        @can('inventory.items.manage')
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="pencil"
                                                wire:click="openEditModal({{ $item->id }})"
                                                title="Edit Item"
                                            />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <x-icon name="inventory_2" class="size-12 text-zinc-300 dark:text-zinc-600" />
                                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                                            {{ __('No items found.') }}
                                        </flux:text>
                                        @can('inventory.items.manage')
                                            <flux:button size="sm" wire:click="openCreateModal">
                                                {{ __('Create your first item') }}
                                            </flux:button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($items->hasPages())
                <div class="mt-4 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $items->links() }}
                </div>
            @endif
        </flux:card>
    </flux:main>

    {{-- Create/Edit Item Modal --}}
    <flux:modal wire:model="showItemModal" class="max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $isEditing ? __('Edit Item') : __('New Item') }}
            </flux:heading>

            <form wire:submit="saveItem" class="space-y-4">
                {{-- Branch Selector for Global Admins (Create only) --}}
                @if ($showBranchSelector && !$isEditing)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                        <flux:select wire:model="branch_id" label="{{ __('Branch') }}" required>
                            <flux:select.option value="">{{ __('-- Select Branch --') }}</flux:select.option>
                            @foreach ($branches as $branch)
                                <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                            {{ __('Select a branch to create this item.') }}
                        </p>
                        @error('branch_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="sku"
                        label="{{ __('SKU') }}"
                        placeholder="Leave blank to auto-generate"
                    />

                    <flux:select wire:model="inventory_category_id" label="{{ __('Category') }}" required>
                        <flux:select.option value="">Select category</flux:select.option>
                        @foreach ($categories as $id => $name)
                            <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                @error('sku') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @error('inventory_category_id') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <flux:input
                    wire:model="name"
                    label="{{ __('Item Name') }}"
                    placeholder="e.g., Cotton Fabric - White"
                    required
                />
                @error('name') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="inventory_unit_id" label="{{ __('Unit') }}" required>
                        <flux:select.option value="">Select unit</flux:select.option>
                        @foreach ($units as $id => $name)
                            <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input
                        wire:model="reorder_level"
                        type="number"
                        label="{{ __('Reorder Level') }}"
                        min="0"
                        required
                    />
                </div>
                @error('inventory_unit_id') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="default_buy_price"
                        type="number"
                        step="1"
                        label="{{ __('Default Buy Price') }}"
                        min="0"
                    />

                    <flux:input
                        wire:model="default_sell_price"
                        type="number"
                        step="1"
                        label="{{ __('Default Sell Price') }}"
                        min="0"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <flux:checkbox wire:model="is_active" id="is_active" />
                    <label for="is_active" class="text-sm text-zinc-700 dark:text-zinc-300">
                        {{ __('Item is active') }}
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <flux:button type="button" variant="ghost" wire:click="closeItemModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit">
                        {{ $isEditing ? __('Update') : __('Create') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Receive Stock Modal --}}
    <flux:modal wire:model="showReceiveModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Receive Stock') }}</flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ $receiveItemName }}
                </flux:text>
            </div>

            <form wire:submit="receiveStock" class="space-y-4">
                <flux:input
                    wire:model="receiveQty"
                    type="number"
                    step="1"
                    min="0.01"
                    label="{{ __('Quantity to Receive') }}"
                    required
                />
                @error('receiveQty') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <flux:input
                    wire:model="receiveUnitCost"
                    type="number"
                    step="1"
                    min="0"
                    label="{{ __('Unit Cost (Optional)') }}"
                />
                @error('receiveUnitCost') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <flux:textarea
                    wire:model="receiveNote"
                    label="{{ __('Note (Optional)') }}"
                    placeholder="e.g., Received from Supplier ABC"
                    rows="2"
                />
                @error('receiveNote') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <div class="flex justify-end gap-3 pt-4">
                    <flux:button type="button" variant="ghost" wire:click="closeReceiveModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" class="bg-green-600 hover:bg-green-700">
                        {{ __('Receive Stock') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Adjust Stock Modal --}}
    <flux:modal wire:model="showAdjustModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Adjust Stock') }}</flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ $adjustItemName }}
                </flux:text>
            </div>

            <div class="rounded-lg bg-zinc-100 p-4 dark:bg-zinc-800">
                <div class="text-sm text-zinc-600 dark:text-zinc-400">Current Stock</div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($adjustCurrentQty, 0) }}</div>
            </div>

            <form wire:submit="adjustStock" class="space-y-4">
                <div>
                    <flux:input
                        wire:model="adjustQty"
                        type="number"
                        step="1"
                        label="{{ __('Adjustment Quantity') }}"
                        placeholder="Use negative for reduction"
                        required
                    />
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Enter positive to add, negative to remove. New stock: {{ number_format($adjustCurrentQty + $adjustQty, 0) }}
                    </p>
                </div>
                @error('adjustQty') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <flux:textarea
                    wire:model="adjustNote"
                    label="{{ __('Reason for Adjustment') }}"
                    placeholder="e.g., Physical count correction, Damaged goods"
                    rows="2"
                    required
                />
                @error('adjustNote') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <div class="flex justify-end gap-3 pt-4">
                    <flux:button type="button" variant="ghost" wire:click="closeAdjustModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" class="bg-amber-600 hover:bg-amber-700">
                        {{ __('Adjust Stock') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>

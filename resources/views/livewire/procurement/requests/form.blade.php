<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('procurement.requests.index') }}" wire:navigate>{{ __('Purchase Requests') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $isEdit ? __('Edit') : __('New') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-circle">
            {{ session('error') }}
        </flux:callout>
    @endif

    {{-- Branch Selection Banner for Global Admins --}}
    @if ($showBranchSelector && !$isEdit && !$branchId)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Select a branch below before searching for products.') }}
        </flux:callout>
    @endif

    <form wire:submit="save">
        {{-- Branch Selector for Global Admins (Create only) --}}
        @if ($showBranchSelector && !$isEdit)
            <flux:card class="mb-6">
                <flux:heading size="lg" class="mb-4">{{ __('Branch Assignment') }}</flux:heading>
                <div class="max-w-md">
                    <flux:select wire:model.live="branchId" label="{{ __('Branch') }}" required>
                        <flux:select.option value="">{{ __('-- Select Branch --') }}</flux:select.option>
                        @foreach ($branches as $branch)
                            <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:text class="mt-2 text-sm text-zinc-500">
                        {{ __('Products will be searched from this branch\'s inventory.') }}
                    </flux:text>
                    @error('branchId')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
            </flux:card>
        @endif

        <flux:card class="mb-6">
            <flux:heading size="xl" class="mb-2">
                {{ $isEdit ? __('Edit Purchase Request') : __('New Purchase Request') }}
            </flux:heading>
            <flux:text class="mb-6 text-zinc-500">
                {{ __('Search and select products from your branch inventory to create a purchase request.') }}
            </flux:text>

            {{-- Product Search Bar --}}
            <div class="mb-6">
                <flux:label class="mb-2">{{ __('Search Products') }}</flux:label>
                <div class="relative">
                    <div class="relative">
                        <flux:icon name="magnifying-glass" class="absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="productSearch"
                            wire:keydown.escape="closeSearchDropdown"
                            placeholder="{{ $canSearch ? __('Search by product name or SKU...') : __('Select a branch first...') }}"
                            class="w-full rounded-lg border border-zinc-300 bg-white py-3 pl-10 pr-4 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:focus:border-indigo-400 {{ !$canSearch ? 'cursor-not-allowed opacity-60' : '' }}"
                            {{ !$canSearch ? 'disabled' : '' }}
                        />
                        @if ($productSearch)
                            <button
                                type="button"
                                wire:click="$set('productSearch', '')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600"
                            >
                                <flux:icon name="x-mark" class="size-5" />
                            </button>
                        @endif
                    </div>

                    {{-- Search Results Dropdown --}}
                    @if ($showSearchDropdown && count($searchResults) > 0)
                        <div
                            class="absolute z-50 mt-1 max-h-80 w-full overflow-auto rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
                            wire:click.outside="closeSearchDropdown"
                        >
                            @foreach ($searchResults as $product)
                                <button
                                    type="button"
                                    wire:click="selectProduct({{ $product['id'] }})"
                                    class="flex w-full items-center justify-between px-4 py-3 text-left transition hover:bg-indigo-50 dark:hover:bg-indigo-900/20"
                                >
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-zinc-900 dark:text-white">{{ $product['name'] }}</span>
                                            @if ($product['sku'])
                                                <span class="rounded bg-zinc-100 px-2 py-0.5 text-xs font-mono text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                                    {{ $product['sku'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="mt-1 flex items-center gap-4 text-sm text-zinc-500 dark:text-zinc-400">
                                            <span>
                                                {{ __('Stock') }}: 
                                                <span class="{{ $product['current_stock'] <= $product['reorder_level'] ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">
                                                    {{ number_format($product['current_stock'], 0) }}
                                                </span>
                                                @if ($product['unit'])
                                                    {{ $product['unit'] }}
                                                @endif
                                            </span>
                                            @if ($product['default_buy_price'] > 0)
                                                <span>{{ __('Est. Price') }}: {{ number_format($product['default_buy_price'], 0) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <flux:icon name="plus-circle" class="size-6 text-indigo-600 dark:text-indigo-400" />
                                </button>
                            @endforeach
                        </div>
                    @elseif ($productSearch && strlen($productSearch) >= 2 && count($searchResults) === 0)
                        <div class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 bg-white p-4 text-center shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
                            <flux:icon name="magnifying-glass" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('No products found for') }} "{{ $productSearch }}"
                            </p>
                            <button
                                type="button"
                                wire:click="addManualItem"
                                class="mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                            >
                                {{ __('+ Add as manual item') }}
                            </button>
                        </div>
                    @endif
                </div>

                @if (!$canSearch && $showBranchSelector)
                    <flux:text class="mt-2 text-sm text-amber-600 dark:text-amber-400">
                        <flux:icon name="exclamation-triangle" class="inline-block size-4 mr-1" />
                        {{ __('Please select a branch above to search products.') }}
                    </flux:text>
                @endif
            </div>

            {{-- Items List --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="md">{{ __('Request Items') }} ({{ count($items) }})</flux:heading>
                    <flux:button type="button" size="sm" variant="ghost" wire:click="addManualItem">
                        <flux:icon name="plus" class="mr-1 size-4" />
                        {{ __('Add Manual Item') }}
                    </flux:button>
                </div>

                @error('items')
                    <flux:callout variant="danger" icon="exclamation-circle">
                        {{ $message }}
                    </flux:callout>
                @enderror

                @if (count($items) === 0)
                    <div class="rounded-lg border-2 border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                        <flux:icon name="shopping-cart" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                        <p class="mt-4 text-zinc-500 dark:text-zinc-400">
                            {{ __('No items added yet. Use the search bar above to find and add products.') }}
                        </p>
                    </div>
                @else
                    {{-- Table Header (Desktop) --}}
                    <div class="hidden rounded-t-lg bg-zinc-100 px-4 py-3 text-xs font-medium uppercase tracking-wider text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 sm:grid sm:grid-cols-12 sm:gap-4">
                        <div class="col-span-5">{{ __('Product') }}</div>
                        <div class="col-span-2 text-center">{{ __('Stock') }}</div>
                        <div class="col-span-2 text-center">{{ __('Qty') }}</div>
                        <div class="col-span-2 text-right">{{ __('Est. Price') }}</div>
                        <div class="col-span-1"></div>
                    </div>

                    <div class="space-y-2">
                        @foreach ($items as $index => $item)
                            <div
                                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50"
                                wire:key="item-{{ $index }}"
                            >
                                <div class="grid gap-4 sm:grid-cols-12 sm:items-center">
                                    {{-- Product Name --}}
                                    <div class="sm:col-span-5">
                                        @if ($item['inventory_item_id'])
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-zinc-900 dark:text-white">{{ $item['item_name'] }}</span>
                                                @if ($item['sku'] ?? null)
                                                    <span class="rounded bg-zinc-100 px-2 py-0.5 text-xs font-mono text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                                                        {{ $item['sku'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <flux:input
                                                wire:model.blur="items.{{ $index }}.item_name"
                                                placeholder="{{ __('Enter item name...') }}"
                                                class="text-sm"
                                            />
                                        @endif
                                        @error("items.{$index}.item_name")
                                            <flux:text class="mt-1 text-xs text-red-500">{{ $message }}</flux:text>
                                        @enderror
                                    </div>

                                    {{-- Current Stock --}}
                                    <div class="sm:col-span-2 sm:text-center">
                                        <span class="text-xs text-zinc-500 sm:hidden">{{ __('Stock') }}: </span>
                                        @if ($item['current_stock'] !== null)
                                            <span class="{{ ($item['current_stock'] ?? 0) <= 10 ? 'text-red-600 dark:text-red-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                                                {{ number_format($item['current_stock'], 0) }}
                                            </span>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </div>

                                    {{-- Quantity --}}
                                    <div class="sm:col-span-2">
                                        <span class="text-xs text-zinc-500 sm:hidden">{{ __('Qty') }}: </span>
                                        <input
                                            type="number"
                                            wire:model.blur="items.{{ $index }}.qty"
                                            step="0.01"
                                            min="0.01"
                                            class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-center text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-white sm:w-20"
                                        />
                                        @error("items.{$index}.qty")
                                            <flux:text class="mt-1 text-xs text-red-500">{{ $message }}</flux:text>
                                        @enderror
                                    </div>

                                    {{-- Estimated Unit Price --}}
                                    <div class="sm:col-span-2 sm:text-right">
                                        <span class="text-xs text-zinc-500 sm:hidden">{{ __('Est. Price') }}: </span>
                                        <input
                                            type="number"
                                            wire:model.blur="items.{{ $index }}.unit_price_est"
                                            step="1"
                                            min="0"
                                            class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-right text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-white sm:w-28"
                                        />
                                    </div>

                                    {{-- Remove Button --}}
                                    <div class="sm:col-span-1 sm:text-right">
                                        <button
                                            type="button"
                                            wire:click="removeItem({{ $index }})"
                                            class="rounded p-1 text-zinc-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                                            title="{{ __('Remove') }}"
                                        >
                                            <flux:icon name="trash" class="size-5" />
                                        </button>
                                    </div>
                                </div>

                                {{-- Line Total (Mobile) --}}
                                <div class="mt-3 flex justify-end border-t border-zinc-100 pt-3 text-sm dark:border-zinc-700 sm:hidden">
                                    <span class="text-zinc-500">{{ __('Line Total') }}:</span>
                                    <span class="ml-2 font-mono font-medium">
                                        {{ money_tzs(($item['qty'] ?? 0) * ($item['unit_price_est'] ?? 0)) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Total --}}
                @if (count($items) > 0)
                    <div class="flex justify-end rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/50">
                        <div class="text-right">
                            <flux:label>{{ __('Estimated Total') }}</flux:label>
                            <flux:heading size="xl" class="font-mono text-indigo-600 dark:text-indigo-400">
                                {{ money_tzs($lineTotal) }}
                            </flux:heading>
                        </div>
                    </div>
                @endif
            </div>
        </flux:card>

        {{-- Note --}}
        <flux:card class="mb-6">
            <flux:label for="note">{{ __('Notes') }}</flux:label>
            <flux:textarea
                id="note"
                wire:model="note"
                rows="3"
                placeholder="{{ __('Optional notes for this request (e.g., urgency, specific requirements)...') }}"
            />
            @error('note')
                <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
            @enderror
        </flux:card>

        {{-- Actions --}}
        <div class="flex items-center justify-between">
            <flux:text class="text-sm text-zinc-500">
                {{ __('After saving, submit the request for accountant review.') }}
            </flux:text>
            <div class="flex gap-3">
                <flux:button type="button" variant="ghost" :href="route('procurement.requests.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <flux:icon name="check" class="mr-1 size-4" wire:loading.remove wire:target="save" />
                    <flux:icon name="arrow-path" class="mr-1 size-4 animate-spin" wire:loading wire:target="save" />
                    {{ $isEdit ? __('Update Request') : __('Save as Draft') }}
                </flux:button>
            </div>
        </div>
    </form>
</flux:main>

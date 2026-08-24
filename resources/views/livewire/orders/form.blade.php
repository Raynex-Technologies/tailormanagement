<div>
    <flux:main class="p-0">
        <x-orders.workspace-header
            :title="$isEdit ? __('Edit Order :order', ['order' => $order->order_no]) : __('Create New Order')"
            :subtitle="$isEdit ? __('Update customer, order contents and fulfillment details.') : __('Create a customer order and configure its contents in one guided workspace.')"
        >
            <x-slot:breadcrumbs>
                <flux:breadcrumbs class="text-white/70">
                    <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                    <flux:breadcrumbs.item :href="route('orders.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Orders') }}</flux:breadcrumbs.item>
                    <flux:breadcrumbs.item class="!text-white">{{ $isEdit ? __('Edit Order') : __('New Order') }}</flux:breadcrumbs.item>
                </flux:breadcrumbs>
            </x-slot:breadcrumbs>

            <x-slot:actions>
                <flux:button variant="outline" :href="route('orders.index')" class="w-full !border-white/25 !bg-white/10 !text-white hover:!bg-white/20 sm:w-auto" wire:navigate>
                    {{ __('Back to Orders') }}
                </flux:button>
            </x-slot:actions>
        </x-orders.workspace-header>

        {{-- Error Display --}}
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                <div class="flex items-start gap-3">
                    <x-icon name="error" class="size-5 text-red-600 dark:text-red-400" />
                    <div class="flex-1">
                        <flux:heading size="sm" class="text-red-800 dark:text-red-200">Please fix the following errors:</flux:heading>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-red-700 dark:text-red-300">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Branch Selection Banner for Global Admins --}}
        @if ($showBranchSelector && !$isEdit && $mustSelectBranch)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                <div class="flex items-center gap-3">
                    <x-icon name="warning" class="size-5 text-amber-600 dark:text-amber-400" />
                    <div>
                        <flux:heading size="sm" class="text-amber-800 dark:text-amber-200">Branch Selection Required</flux:heading>
                        <p class="text-sm text-amber-700 dark:text-amber-300">
                            Select a branch below to create this order. Orders and customers will be assigned to the selected branch.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <form id="order-form" wire:submit.prevent="save" class="space-y-6">
            {{-- Compact order context and scheduling --}}
            <flux:card class="overflow-visible" data-order-setup>
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('Order Setup') }}</flux:heading>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Set the customer, schedule and responsibility before adding order contents.') }}</p>
                    </div>
                    @if ((!$showBranchSelector || $isEdit) && $effectiveBranchName)
                        <span class="inline-flex items-center gap-2 rounded-full bg-zinc-100 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:bg-white/5 dark:text-zinc-300" data-order-branch-context>
                            <i class="fa-duotone fa-code-branch" aria-hidden="true"></i>
                            {{ __('Branch: :branch', ['branch' => $effectiveBranchName]) }}
                        </span>
                    @endif
                </div>

                <div class="grid gap-5 lg:grid-cols-12">
                    @if ($showBranchSelector && !$isEdit)
                        <div class="lg:col-span-3" data-order-branch-selector>
                            <flux:select wire:model.live="branch_id" aria-label="{{ __('Order branch') }}" title="{{ __('Sets available order options.') }}" required>
                                <flux:select.option value="">-- Select Branch --</flux:select.option>
                                @foreach ($branches as $branch)
                                    <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('branch_id')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <section class="{{ $showBranchSelector && !$isEdit ? 'lg:col-span-9' : 'lg:col-span-12' }}" aria-labelledby="order-customer-heading" data-order-customer>
                        <h2 id="order-customer-heading" class="sr-only">{{ __('Customer') }}</h2>

                        @if (!$showNewCustomerForm)
                            @if ($selectedCustomer)
                                <div class="flex flex-col gap-3 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-3 dark:border-emerald-500/20 dark:bg-emerald-500/5 sm:flex-row sm:items-center sm:justify-between" data-selected-customer>
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $selectedCustomer->name }}</p>
                                        <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ $selectedCustomer->phone ?: __('No phone') }}
                                            @if($selectedCustomer->code)
                                                <span aria-hidden="true"> · </span>{{ $selectedCustomer->code }}
                                            @endif
                                            @if($selectedCustomer->email)
                                                <span class="hidden sm:inline"><span aria-hidden="true"> · </span>{{ $selectedCustomer->email }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="clearSelectedCustomer" class="w-full sm:w-auto">
                                        {{ __('Change') }}
                                    </flux:button>
                                </div>
                            @else
                                <div
                                    class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]"
                                    x-data="{ open: @entangle('showCustomerDropdown') }"
                                    @click.outside="open = false"
                                >
                                    <div class="relative min-w-0">
                                        <flux:input
                                            wire:model.live="customerSearch"
                                            wire:focus="showCustomerDropdown = true"
                                            aria-label="{{ __('Search existing customer') }}"
                                            placeholder="{{ __('Search by name, phone or email') }}"
                                            icon="magnifying-glass"
                                            :disabled="$showBranchSelector && !$isEdit && !$branch_id"
                                            autocomplete="off"
                                            role="combobox"
                                            aria-autocomplete="list"
                                            :aria-expanded="$showCustomerDropdown ? 'true' : 'false'"
                                            aria-controls="order-customer-results"
                                        />
                                        <p wire:loading.delay wire:target="customerSearch" class="mt-1 text-xs text-zinc-500" role="status">{{ __('Searching customers…') }}</p>

                                        @if ($showCustomerDropdown && count($customers) > 0)
                                            <div id="order-customer-results" class="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800" role="listbox">
                                                @foreach ($customers as $customer)
                                                    <button
                                                        type="button"
                                                        wire:click="selectCustomer({{ $customer->id }})"
                                                        class="flex w-full items-start justify-between gap-3 px-4 py-3 text-left hover:bg-zinc-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-lime-500 dark:hover:bg-zinc-700"
                                                        role="option"
                                                    >
                                                        <span class="min-w-0">
                                                            <span class="block truncate font-medium text-zinc-900 dark:text-white">{{ $customer->name }}</span>
                                                            <span class="block truncate text-xs text-zinc-500">
                                                                {{ $customer->phone ?: __('No phone') }}
                                                                @if($customer->email)<span aria-hidden="true"> · </span>{{ $customer->email }}@endif
                                                            </span>
                                                        </span>
                                                        <span class="shrink-0 text-xs text-zinc-400">{{ $customer->code }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @elseif ($showCustomerDropdown && strlen($customerSearch) >= 2 && count($customers) === 0)
                                            <div id="order-customer-results" class="absolute z-50 mt-1 w-full rounded-xl border border-zinc-200 bg-white p-4 text-center text-sm text-zinc-500 shadow-lg dark:border-zinc-700 dark:bg-zinc-800" role="status">
                                                {{ __('No customers found for this branch.') }}
                                            </div>
                                        @endif
                                    </div>

                                    <flux:button
                                        type="button"
                                        size="base"
                                        variant="primary"
                                        wire:click="toggleNewCustomerForm"
                                        class="inline-flex w-full items-center justify-center gap-2 self-end sm:w-auto"
                                        :disabled="$showBranchSelector && !$isEdit && !$branch_id"
                                        aria-expanded="false"
                                        aria-controls="new-order-customer-fields"
                                    >
                                        <x-icon name="contacts_product" class="size-5 shrink-0" />
                                        <span class="text-center">{{ __('New Customer') }}</span>
                                    </flux:button>
                                </div>

                                @if ($showBranchSelector && !$isEdit && !$branch_id)
                                    <p class="mt-2 text-sm text-amber-600 dark:text-amber-300">{{ __('Select a branch before searching for or creating a customer.') }}</p>
                                @endif
                            @endif
                        @else
                            <div
                                id="new-order-customer-fields"
                                class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-white/5"
                                x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                                data-new-customer-form
                            >
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <flux:heading size="sm">{{ __('New Customer') }}</flux:heading>
                                        <p class="mt-1 text-xs text-zinc-500">{{ __('The customer will be created in the order branch when the order is saved.') }}</p>
                                    </div>
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="toggleNewCustomerForm" aria-expanded="true" aria-controls="new-order-customer-fields">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <flux:input wire:model="newCustomerName" label="Name" placeholder="Customer name" required />
                                        @error('newCustomerName')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <flux:input wire:model="newCustomerPhone" label="Phone" placeholder="+255..." required />
                                        @error('newCustomerPhone')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <flux:input wire:model="newCustomerEmail" label="Email" type="email" placeholder="email@example.com" />
                                        @error('newCustomerEmail')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <flux:input wire:model="newCustomerAddress" label="Address" placeholder="Customer address" />
                                        @error('newCustomerAddress')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        @endif

                        @error('customer_id')
                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </section>
                </div>

                <div class="mt-5 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @if ($allowOrderDatesFlexibility)
                        <flux:input
                            wire:model="order_date"
                            type="date"
                            label="Order Date"
                            required
                        />
                    @else
                        <flux:input
                            wire:model="order_date"
                            type="date"
                            label="Order Date"
                            min="{{ $isEdit ? ($order?->order_date?->toDateString() ?? $order?->created_at?->toDateString() ?? date('Y-m-d')) : date('Y-m-d') }}"
                            required
                        />
                    @endif

                    @if ($allowOrderDatesFlexibility)
                        <flux:input
                            wire:model="due_date"
                            type="date"
                            label="Due Date"
                        />
                    @else
                        <flux:input
                            wire:model="due_date"
                            type="date"
                            label="Due Date"
                            min="{{ date('Y-m-d') }}"
                        />
                    @endif

                    <flux:select wire:model="priority" label="Priority">
                        @foreach ($priorities as $p)
                            <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @can('orders.assign_tailor')
                        <flux:select wire:model.live="assigned_tailor_id" label="Order Tailor (Optional)">
                            <flux:select.option value="">-- No Order Tailor --</flux:select.option>
                            @foreach ($tailors as $tailor)
                                <flux:select.option value="{{ $tailor->id }}">{{ $tailor->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endcan
                    </div>
                </div>
            </flux:card>

            {{-- Order Contents --}}
            <flux:card>
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div><flux:heading size="lg">{{ __('Order Contents') }}</flux:heading><p class="mt-1 text-sm text-zinc-500">{{ __('Combine catalog selections with custom order lines.') }}</p></div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="primary" wire:click="toggleCatalogPicker" type="button">
                            <i class="fa-duotone fa-grid-2-plus mr-1.5"></i>
                            {{ __('Add from Catalog') }}
                        </flux:button>
                        <flux:button size="sm" variant="subtle" wire:click="addLine" type="button">
                            <x-icon name="add" class="mr-1 size-4" />
                            {{ __('Add Custom Item') }}
                        </flux:button>
                    </div>
                </div>

                @if ($showCatalogPicker)
                    <div class="mb-5 rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-white/5" data-order-catalog-picker>
                        <div class="grid grid-cols-3 gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-900/60">
                            <button type="button" wire:click="setCatalogTab('packages')" class="rounded-lg px-2 py-2 text-sm font-medium {{ $catalogTab === 'packages' ? 'bg-white shadow dark:bg-zinc-700' : 'text-zinc-500' }}">{{ __('Packages') }}</button>
                            <button type="button" wire:click="setCatalogTab('catalog')" class="rounded-lg px-2 py-2 text-sm font-medium {{ $catalogTab === 'catalog' ? 'bg-white shadow dark:bg-zinc-700' : 'text-zinc-500' }}">{{ __('Garments & Services') }}</button>
                            <button type="button" wire:click="setCatalogTab('inventory')" class="rounded-lg px-2 py-2 text-sm font-medium {{ $catalogTab === 'inventory' ? 'bg-white shadow dark:bg-zinc-700' : 'text-zinc-500' }}">{{ __('Inventory Products') }}</button>
                        </div>
                        <flux:input wire:model.live.debounce.300ms="catalogSearch" class="mt-3" icon="magnifying-glass" placeholder="{{ __('Search the selected catalog source') }}" />
                        @if ($showBranchSelector && ! $isEdit && ! $branch_id)<p class="mt-3 text-sm text-amber-600">{{ __('Select an order branch to browse catalog content.') }}</p>@endif

                        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @if ($catalogTab === 'packages')
                                @forelse ($catalogPackages as $catalogPackage)
                                    <button type="button" wire:click="configurePackage({{ $catalogPackage->id }})" class="overflow-hidden rounded-xl border border-zinc-200 text-left transition hover:border-lime-400 dark:border-zinc-700">
                                        <div class="flex gap-3 p-3"><div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-700">@if($catalogPackage->cover_image_url)<img src="{{ $catalogPackage->cover_image_url }}" alt="" class="size-full object-cover">@else<i class="fa-duotone fa-box-open-full text-zinc-400"></i>@endif</div><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ $catalogPackage->name }}</p><p class="line-clamp-2 text-xs text-zinc-500">{{ $catalogPackage->description }}</p><p class="mt-1 text-xs">{{ money_currency($catalogPackage->pricing_summary['package_price']) }} · {{ trans_choice(':count component|:count components', $catalogPackage->items->count(), ['count' => $catalogPackage->items->count()]) }}</p>@if((float)$catalogPackage->pricing_summary['difference'] > 0)<p class="text-xs text-emerald-600">{{ __('Save :amount', ['amount' => money_currency($catalogPackage->pricing_summary['difference'])]) }}</p>@endif</div></div>
                                    </button>
                                @empty <p class="col-span-full rounded-xl border border-dashed p-5 text-center text-sm text-zinc-500">{{ __('No active packages found for this branch.') }}</p> @endforelse
                            @elseif ($catalogTab === 'catalog')
                                @forelse ($catalogItems as $catalogItem)
                                    <button type="button" wire:click="configureDirectCatalogItem({{ $catalogItem->id }})" class="flex gap-3 rounded-xl border border-zinc-200 p-3 text-left transition hover:border-lime-400 dark:border-zinc-700"><div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-700">@if($catalogItem->image_url)<img src="{{ $catalogItem->image_url }}" alt="" class="size-full object-cover">@else<i class="fa-duotone fa-shirt text-zinc-400"></i>@endif</div><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ $catalogItem->name }}</p><p class="text-xs text-zinc-500">{{ $catalogItem->code }} · {{ $catalogItem->type->label() }}</p><p class="mt-1 text-xs font-medium">{{ money_currency($catalogItem->default_selling_price) }}</p></div></button>
                                @empty <p class="col-span-full rounded-xl border border-dashed p-5 text-center text-sm text-zinc-500">{{ __('No active garments or services found for this branch.') }}</p> @endforelse
                            @else
                                @forelse ($inventoryItems as $inventoryItem)
                                    @php
                                        $availableQty = (float) ($inventoryItem->stock?->qty_on_hand ?? 0)
                                            - (float) ($inventoryItem->stock?->qty_reserved ?? 0);
                                    @endphp
                                    <button type="button" wire:click="addInventoryLine({{ $inventoryItem->id }})" class="flex gap-3 rounded-xl border border-zinc-200 p-3 text-left transition hover:border-lime-400 dark:border-zinc-700"><div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-700">@if($inventoryItem->featured_image_url)<img src="{{ $inventoryItem->featured_image_url }}" alt="" class="size-full object-cover">@else<i class="fa-duotone fa-box text-zinc-400"></i>@endif</div><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ $inventoryItem->name }}</p><p class="text-xs text-zinc-500">{{ $inventoryItem->sku ?: __('No SKU') }} · {{ __('Available: :qty', ['qty' => rtrim(rtrim(number_format($availableQty, 2), '0'), '.')]) }}</p><p class="mt-1 text-xs font-medium">{{ money_currency($inventoryItem->default_sell_price) }}</p></div></button>
                                @empty <p class="col-span-full rounded-xl border border-dashed p-5 text-center text-sm text-zinc-500">{{ __('No active inventory products found for this branch.') }}</p> @endforelse
                            @endif
                        </div>
                    </div>
                @endif

                @php
                    $hasOrderTailor = (int) ($assigned_tailor_id ?? 0) > 0;
                @endphp

                @if ($lines === [])
                    <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50/60 px-4 py-5 text-center dark:border-zinc-700 dark:bg-white/[0.03]" data-order-contents-empty>
                        <div class="mx-auto flex size-9 items-center justify-center rounded-lg bg-white text-zinc-400 shadow-sm dark:bg-zinc-800">
                            <i class="fa-duotone fa-scissors text-lg" aria-hidden="true"></i>
                        </div>
                        <p class="mt-2 font-medium text-zinc-800 dark:text-zinc-100">{{ __('No items added yet') }}</p>
                        <p class="mx-auto mt-1 max-w-lg text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add a package, garment, service, inventory product or custom item to begin.') }}</p>
                    </div>
                @else
                <div class="space-y-6" data-order-lines>
                    @php($previousPackageKey = null)
                    @foreach ($lines as $index => $line)
                        @php($linePackageKey = $line['package_key'] ?? null)
                        @if ($linePackageKey && $linePackageKey !== $previousPackageKey && isset($packages[$linePackageKey]))
                            @php($packageState = $packages[$linePackageKey]['configured_snapshot'])
                            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4 dark:border-indigo-500/20 dark:bg-indigo-500/5" wire:key="package-heading-{{ $linePackageKey }}">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div><div class="flex items-center gap-2"><i class="fa-duotone fa-box-open-full text-indigo-500"></i><h3 class="font-semibold uppercase tracking-wide text-zinc-900 dark:text-white">{{ $packageState['name'] }}</h3></div><p class="mt-1 text-sm text-zinc-500">{{ money_currency($packageState['configured_package_total']) }} · {{ __('Source revision :revision', ['revision' => $packageState['revision']]) }}</p></div>
                                    <div class="flex gap-2"><flux:button type="button" size="sm" variant="subtle" wire:click="customizePackage('{{ $linePackageKey }}')">{{ __('Customize Package') }}</flux:button><flux:button type="button" size="sm" variant="ghost" wire:click="removePackage('{{ $linePackageKey }}')" wire:confirm="{{ $isEdit ? __('Remove this package and all of its lines? Inventory changes occur only when the order is saved.') : __('Remove this package and all of its lines?') }}">{{ __('Remove Package') }}</flux:button></div>
                                </div>
                            </div>
                        @endif
                        <div class="rounded-xl border {{ $linePackageKey ? 'ml-3 border-indigo-100 bg-white dark:border-indigo-500/20 dark:bg-zinc-800' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50' }} p-4" wire:key="line-{{ $line['id'] ?? 'new' }}-{{ $linePackageKey ?? 'ordinary' }}-{{ $line['order_package_template_item_id'] ?? 'custom' }}-{{ $line['package_unit_index'] ?? $index }}">
                            {{-- Line Header --}}
                            <div class="mb-4 flex items-start justify-between">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge size="sm">Item {{ $index + 1 }}</flux:badge>
                                    @if (! empty($line['inventory_item_id']))
                                        <flux:badge size="sm" color="lime">Inventory</flux:badge>
                                    @endif
                                    @if (! empty($line['order_catalog_item_id']))
                                        <flux:badge size="sm" color="indigo">{{ __('Catalog') }}</flux:badge>
                                    @endif
                                    @if ($linePackageKey)
                                        <flux:badge size="sm" color="violet">{{ __('Package item') }}</flux:badge>
                                    @endif
                                </div>
                                @if (! $linePackageKey)
                                    <flux:button size="xs" variant="ghost" wire:click="removeLine({{ $index }})" type="button" title="Remove Item">
                                        <x-icon name="delete" class="size-4 text-red-500" />
                                    </flux:button>
                                @endif
                            </div>

                            {{-- Line Details --}}
                            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 {{ $hasOrderTailor ? 'xl:grid-cols-5' : 'xl:grid-cols-6' }}">
                                <div class="lg:col-span-2">
                                    <flux:input
                                        wire:model="lines.{{ $index }}.item_name"
                                        label="Item Name"
                                        placeholder="e.g. Men's Suit"
                                        required
                                        :disabled="(bool) $linePackageKey"
                                    />
                                    @if (! empty($line['sku']))
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">SKU: {{ $line['sku'] }}</p>
                                    @endif
                                    @error("lines.$index.inventory_item_id")
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                    @error("lines.$index.item_name")
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                @can('orders.assign_tailor')
                                    @if (!$hasOrderTailor)
                                        <flux:select wire:model.live="lines.{{ $index }}.assigned_tailor_id" label="Line Tailor">
                                            <flux:select.option value="">-- No Specific Tailor --</flux:select.option>
                                            @foreach ($tailors as $tailor)
                                                <flux:select.option value="{{ $tailor->id }}">{{ $tailor->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @endif
                                @endcan
                                <flux:input
                                    wire:model.live.debounce.250ms="lines.{{ $index }}.qty"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    label="Qty"
                                    required
                                    :disabled="(bool) $linePackageKey"
                                />
                                @error("lines.$index.qty")
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                                <x-money-input
                                    wire:model.blur="lines.{{ $index }}.unit_price"
                                    step="1"
                                    min="0"
                                    label="Unit Price"
                                    required
                                    :disabled="(bool) $linePackageKey"
                                />
                                @error("lines.$index.unit_price")
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Line Total</label>
                                    <div class="flex h-10 items-center rounded-lg bg-zinc-100 px-3 font-mono text-zinc-900 dark:bg-zinc-700 dark:text-white">
                                        {{ number_format($lines[$index]['line_total'] ?? 0, 0) }}
                                    </div>
                                </div>
                            </div>

                            {{-- Measurements --}}
                            <div class="mt-4">
                                <div class="mb-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Measurements</label>
                                    <flux:button size="xs" variant="ghost" wire:click="addMeasurement({{ $index }})" type="button">
                                        <x-icon name="add" class="mr-1 size-3" />
                                        Add
                                    </flux:button>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($line['measurements'] ?? [] as $mIndex => $measurement)
                                        <div class="flex items-center gap-2" wire:key="line-{{ $index }}-measurement-{{ $mIndex }}">
                                            <flux:input
                                                wire:model="lines.{{ $index }}.measurements.{{ $mIndex }}.key"
                                                placeholder="e.g. Chest"
                                                class="flex-1"
                                            />
                                            <flux:input
                                                wire:model="lines.{{ $index }}.measurements.{{ $mIndex }}.value"
                                                placeholder="e.g. 42 in"
                                                class="flex-1"
                                            />
                                            @if (count($line['measurements']) > 1)
                                                <flux:button size="xs" variant="ghost" wire:click="removeMeasurement({{ $index }}, {{ $mIndex }})" type="button">
                                                    <x-icon name="close" class="size-4 text-zinc-400" />
                                                </flux:button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @php($previousPackageKey = $linePackageKey)
                    @endforeach
                </div>
                @endif

                @error('lines')<p class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ $message }}</p>@enderror

                {{-- Commercial adjustments and server-authoritative totals --}}
                <div class="mt-6 border-t border-zinc-200 pt-5 dark:border-zinc-700" data-order-commercial-summary>
                    <div class="w-full rounded-xl bg-zinc-50/80 p-4 ring-1 ring-inset ring-zinc-200/70 dark:bg-white/5 dark:ring-white/10" data-order-commercial-footer>
                        <div class="grid gap-4 lg:grid-cols-[minmax(13rem,18rem)_minmax(0,1fr)] lg:items-end">
                            <div>
                                <x-money-input
                                    wire:model.blur="discount"
                                    step="1"
                                    min="0"
                                    label="Discount"
                                    placeholder="0.00"
                                />
                                @error('discount')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Subtotal</dt>
                                    <dd class="mt-1 font-mono text-sm font-medium text-zinc-900 dark:text-white">{{ money_currency($subtotal) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Discount</dt>
                                    <dd class="mt-1 font-mono text-sm font-medium text-red-600 dark:text-red-400">-{{ money_currency($discount ?? 0) }}</dd>
                                </div>
                                <div class="col-span-2 border-t border-zinc-200 pt-3 sm:col-span-1 sm:border-t-0 sm:border-l sm:pl-4 sm:pt-0 dark:border-zinc-700">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-300">Total</dt>
                                    <dd class="mt-1 font-mono text-lg font-semibold text-indigo-600 dark:text-indigo-400">{{ money_currency($total) }}</dd>
                                </div>
                            </dl>
                        </div>
                        @error('total')<p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ $message }}</p>@enderror
                    </div>
                </div>
            </flux:card>

            {{-- Secondary order information --}}
            <section class="rounded-xl border border-zinc-200/80 bg-zinc-50/40 p-4 dark:border-zinc-700/80 dark:bg-white/[0.025]" data-order-additional-information>
                <flux:heading size="lg">{{ __('Additional Information') }}</flux:heading>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add optional internal notes for this order.') }}</p>

                <div class="mt-4" data-order-notes>
                    <flux:textarea
                        wire:model="notes"
                        label="Order Notes"
                        placeholder="Additional notes about this order..."
                        rows="2"
                    />
                    @error('notes')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

            </section>

            <section class="rounded-xl border border-zinc-200/80 bg-zinc-50/40 p-4 dark:border-zinc-700/80 dark:bg-white/[0.025]" data-order-expenses>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-zinc-900 dark:text-white">{{ __('Order Expenses') }}</h3>
                            @if ($order_expenses === [])
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No expenses added.') }}</p>
                            @endif
                        </div>
                        <flux:button type="button" size="sm" variant="ghost" wire:click="addOrderExpense" class="w-full sm:w-auto">
                            <x-icon name="add" class="mr-1 size-4" />
                            {{ __('Add Expense') }}
                        </flux:button>
                    </div>

                    @if ($order_expenses !== [])
                        <div class="mt-4 space-y-4">
                            @foreach ($order_expenses as $expenseIndex => $expense)
                                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-white/5" wire:key="order-expense-{{ $expenseIndex }}">
                                    <div class="mb-4 flex items-start justify-between gap-3">
                                        <flux:badge size="sm">Expense {{ $expenseIndex + 1 }}</flux:badge>
                                        <flux:button type="button" size="xs" variant="ghost" wire:click="removeOrderExpense({{ $expenseIndex }})">
                                            {{ __('Remove') }}
                                        </flux:button>
                                    </div>

                                    <input type="hidden" wire:model="order_expenses.{{ $expenseIndex }}.tailor_id" />

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <flux:select wire:model.blur="order_expenses.{{ $expenseIndex }}.notes" label="Description">
                                            <flux:select.option value="">{{ __('Select description') }}</flux:select.option>
                                            <flux:select.option value="Labour Charge">{{ __('Labour Charge') }}</flux:select.option>
                                            <flux:select.option value="Additional Materials">{{ __('Additional Materials') }}</flux:select.option>
                                            <flux:select.option value="Other">{{ __('Other') }}</flux:select.option>
                                        </flux:select>
                                        <x-money-input
                                            wire:model.blur="order_expenses.{{ $expenseIndex }}.amount"
                                            step="1"
                                            min="0"
                                            label="Amount"
                                            placeholder="0.00"
                                        />
                                    </div>

                                    @error("order_expenses.$expenseIndex.tailor_id")<p class="mt-2 text-sm text-red-500">{{ $message }}</p>@enderror
                                    @error("order_expenses.$expenseIndex.notes")<p class="mt-2 text-sm text-red-500">{{ $message }}</p>@enderror
                                    @error("order_expenses.$expenseIndex.amount")<p class="mt-2 text-sm text-red-500">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @error('order_expenses')<p class="mt-2 text-sm text-red-500">{{ $message }}</p>@enderror
            </section>

            {{-- Deposit (create only, optional) --}}
            @if (!$isEdit)
                <section class="rounded-xl border border-zinc-200/80 bg-zinc-50/40 p-4 dark:border-zinc-700/80 dark:bg-white/[0.025]" data-order-deposit>
                    <flux:heading size="lg">Deposit</flux:heading>
                    <flux:text class="mt-1 mb-4 block text-sm text-zinc-500 dark:text-zinc-400">
                        Optionally record a deposit paid with this order. It will be added to the order&apos;s payments.
                    </flux:text>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-money-input
                            wire:model.blur="deposit_amount"
                            step="1"
                            min="0"
                            :max="$total"
                            label="Deposit amount"
                            placeholder="0.00"
                        />
                        @error('deposit_amount')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                        @if (filled($deposit_amount) && (float) $deposit_amount > (float) $total)
                            <p class="text-sm text-red-500">{{ __('Deposit cannot exceed order total.') }}</p>
                        @endif
                        <flux:select wire:model.blur="deposit_payment_method_id" label="Payment method">
                            <flux:select.option value="">-- Select Payment Method --</flux:select.option>
                            @foreach ($paymentMethods as $paymentMethod)
                                <flux:select.option value="{{ $paymentMethod->id }}">
                                    {{ $paymentMethod->display_name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('deposit_payment_method_id')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                        <flux:input
                            wire:model.blur="deposit_reference"
                            label="Reference"
                            placeholder="e.g. receipt or cheque number"
                        />
                        @error('deposit_reference')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </section>
            @endif

            <section class="border-t border-zinc-200 pt-5 dark:border-zinc-700" data-form-actions="order">
                <div class="grid gap-2 sm:grid-flow-col sm:auto-cols-max sm:justify-end">
                    <flux:button variant="ghost" :href="route('orders.index')" class="w-full sm:w-auto" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="check" class="w-full sm:w-auto" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ $isEdit ? __('Update Order') : __('Create Order') }}</span>
                        <span wire:loading>{{ __('Saving…') }}</span>
                    </flux:button>
                </div>
            </section>

        </form>

        <flux:modal wire:model.self="showDirectCatalogConfigurator" class="w-full max-w-lg">
            <div class="space-y-5">
                <div><flux:heading size="lg">{{ __('Add Catalog Item') }}</flux:heading><flux:text class="mt-1">{{ __('Choose the order quantity before adding this item.') }}</flux:text></div>
                @if ($selectedCatalogItem)
                    <div class="flex gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-white/5"><div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white dark:bg-zinc-700">@if($selectedCatalogItem->image_url)<img src="{{ $selectedCatalogItem->image_url }}" alt="" class="size-full object-cover">@else<i class="fa-duotone fa-shirt text-zinc-400"></i>@endif</div><div><p class="font-semibold">{{ $selectedCatalogItem->name }}</p><p class="text-xs text-zinc-500">{{ $selectedCatalogItem->code }} · {{ $selectedCatalogItem->quantity_behavior->label() }}</p><p class="mt-1 text-sm">{{ money_currency($selectedCatalogItem->default_selling_price) }}</p></div></div>
                    <flux:input wire:model="directCatalogQuantity" type="number" min="0.01" step="{{ $selectedCatalogItem->quantity_behavior->value === 'individual' ? '1' : '0.01' }}" label="{{ __('Quantity') }}" />
                    @if ($selectedCatalogItem->quantity_behavior->value === 'individual')<p class="text-xs text-zinc-500">{{ __('Each unit will become an independent line with its own measurements and tailor controls.') }}</p>@else<p class="text-xs text-zinc-500">{{ __('This quantity remains together on one order line.') }}</p>@endif
                    @error('directCatalogQuantity')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @endif
                <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('showDirectCatalogConfigurator', false)">{{ __('Cancel') }}</flux:button><flux:button type="button" variant="primary" wire:click="confirmDirectCatalogItem">{{ __('Add to Order') }}</flux:button></div>
            </div>
        </flux:modal>

        <flux:modal wire:model.self="showPackageConfigurator" class="w-full max-w-4xl">
            <div class="space-y-5">
                <div><flux:heading size="xl">{{ $packageConfigurator['name'] ?? __('Configure Package') }}</flux:heading><flux:text class="mt-1">{{ $packageConfigurator['description'] ?? '' }}</flux:text></div>
                <div class="max-h-[60vh] space-y-3 overflow-y-auto pr-1">
                    @foreach ($packageConfigurator['components'] ?? [] as $packageComponent)
                        @php($packageComponentId = (int) $packageComponent['template_item_id'])
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><p class="font-semibold">{{ $packageComponent['name'] }}</p><flux:badge size="sm" color="{{ (float) $packageComponent['minimum_quantity'] > 0 ? 'indigo' : 'zinc' }}">{{ (float) $packageComponent['minimum_quantity'] > 0 ? __('Required') : __('Optional') }}</flux:badge></div><p class="text-xs text-zinc-500">{{ __('Package unit price: :price', ['price' => money_currency($packageComponent['package_unit_price'])]) }} · {{ __('Default: :quantity', ['quantity' => $packageComponent['default_quantity']]) }}</p></div><div class="w-full sm:w-40"><flux:input wire:model.live.debounce.200ms="packageQuantities.{{ $packageComponentId }}" type="number" step="0.01" min="{{ $packageComponent['minimum_quantity'] }}" max="{{ $packageComponent['maximum_quantity'] }}" label="{{ __('Configured quantity') }}" /></div></div>
                            <p class="mt-2 text-xs text-zinc-500">{{ __('Allowed: :minimum to :maximum', ['minimum' => $packageComponent['minimum_quantity'], 'maximum' => $packageComponent['maximum_quantity'] ?? __('no maximum')]) }}</p>
                        </div>
                    @endforeach
                </div>
                @error('packageQuantities')<p class="rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ $message }}</p>@enderror
                <div class="flex flex-col gap-3 rounded-xl bg-zinc-50 p-4 dark:bg-white/5 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Configured package total') }}</p><p class="text-xl font-semibold">{{ money_currency($packageConfigurationPreview['configured_package_total'] ?? 0) }}</p></div><div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="$set('showPackageConfigurator', false)">{{ __('Cancel') }}</flux:button><flux:button type="button" variant="primary" wire:click="confirmPackageConfiguration">{{ $configuringPackageKey ? __('Update Package') : __('Add Package') }}</flux:button></div></div>
            </div>
        </flux:modal>
    </flux:main>
</div>

<div>
    <flux:main class="p-0">
        {{-- Page Header --}}
        <div class="mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
                <flux:breadcrumbs.item :href="route('orders.index')" wire:navigate>Orders</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $isEdit ? 'Edit' : 'Create' }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <flux:heading size="xl" class="mt-2">
                {{ $isEdit ? "Edit Order {$order->order_no}" : 'Create New Order' }}
            </flux:heading>
        </div>

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

        <form wire:submit.prevent="save" class="space-y-6">
            {{-- Branch Selector for Global Admins (Create only) --}}
            @if ($showBranchSelector && !$isEdit)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Branch Assignment</flux:heading>
                    <div class="max-w-md">
                        <flux:select wire:model.live="branch_id" label="Branch" required>
                            <flux:select.option value="">-- Select Branch --</flux:select.option>
                            @foreach ($branches as $branch)
                                <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            The order and any new customer will be assigned to this branch.
                        </p>
                        @error('branch_id')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </flux:card>
            @endif

            {{-- Customer Section --}}
            <flux:card class="overflow-visible">
                <flux:heading size="lg" class="mb-4">Customer</flux:heading>

                @if (!$showNewCustomerForm)
                    {{-- Search row: reduced-width search + Add New Customer button --}}
                    <div class="flex flex-wrap items-start gap-3" x-data="{ open: @entangle('showCustomerDropdown') }" @click.outside="open = false">
                        <div class="relative min-w-0 flex-1" style="max-width: 320px;">
                            <flux:input
                                wire:model.live="customerSearch"
                                wire:focus="showCustomerDropdown = true"
                                placeholder="Search by name, phone..."
                                icon="magnifying-glass"
                                :disabled="$customer_id !== null"
                                autocomplete="off"
                            />
                            {{-- Customer Search Dropdown --}}
                            @if ($showCustomerDropdown && count($customers) > 0)
                                <div class="absolute z-50 mt-1 w-full rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                    @foreach ($customers as $customer)
                                        <button
                                            type="button"
                                            wire:click="selectCustomer({{ $customer->id }})"
                                            class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-700"
                                        >
                                            <div>
                                                <span class="font-medium text-zinc-900 dark:text-white">{{ $customer->name }}</span>
                                                @if($customer->phone)
                                                    <span class="ml-2 text-sm text-zinc-500">{{ $customer->phone }}</span>
                                                @endif
                                            </div>
                                            <span class="text-xs text-zinc-400">{{ $customer->code }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif ($showCustomerDropdown && strlen($customerSearch) >= 2 && $showBranchSelector && !$isEdit && !$branch_id)
                                <div class="absolute z-50 mt-1 w-full rounded-xl border border-amber-200 bg-amber-50 p-4 text-center text-sm text-amber-700 shadow-lg dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                                    Select a branch above to search customers.
                                </div>
                            @elseif ($showCustomerDropdown && strlen($customerSearch) >= 2 && count($customers) === 0)
                                <div class="absolute z-50 mt-1 w-full rounded-xl border border-zinc-200 bg-white p-4 text-center text-sm text-zinc-500 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                    No customers found. Add a new customer below.
                                </div>
                            @endif
                        </div>
                        <flux:button type="button" size="base" variant="primary" wire:click="toggleNewCustomerForm" class="ml-auto shrink-0">
                            <x-icon name="contacts_product" class="mr-2 size-5" />
                            Add New Customer
                        </flux:button>
                    </div>

                    {{-- Selected customer: read-only details with X to clear --}}
                    @if ($selectedCustomer)
                        <div class="relative mt-4 rounded-xl border border-green-200 bg-green-50/50 p-4 dark:border-green-800 dark:bg-green-900/20">
                            <button
                                type="button"
                                wire:click="clearSelectedCustomer"
                                class="absolute right-3 top-3 rounded-full p-1 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-600 dark:hover:bg-zinc-600 dark:hover:text-zinc-200"
                                title="Change customer"
                            >
                                <x-icon name="close" class="size-5" />
                            </button>
                            <flux:heading size="sm" class="mb-3 pr-8 text-green-800 dark:text-green-200">Selected customer</flux:heading>
                            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="font-medium text-zinc-500 dark:text-zinc-400">Name</dt>
                                    <dd class="text-zinc-900 dark:text-white">{{ $selectedCustomer->name }}</dd>
                                </div>
                                @if($selectedCustomer->phone)
                                    <div>
                                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">Phone</dt>
                                        <dd class="text-zinc-900 dark:text-white">{{ $selectedCustomer->phone }}</dd>
                                    </div>
                                @endif
                                @if($selectedCustomer->email)
                                    <div>
                                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">Email</dt>
                                        <dd class="text-zinc-900 dark:text-white">{{ $selectedCustomer->email }}</dd>
                                    </div>
                                @endif
                                @if($selectedCustomer->address)
                                    <div class="sm:col-span-2">
                                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">Address</dt>
                                        <dd class="text-zinc-900 dark:text-white">{{ $selectedCustomer->address }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @endif
                @else
                    {{-- New Customer Form --}}
                    <div class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <flux:heading size="sm">New Customer</flux:heading>
                            <flux:button size="sm" variant="ghost" wire:click="toggleNewCustomerForm">
                                Cancel
                            </flux:button>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input
                                wire:model="newCustomerName"
                                label="Name"
                                placeholder="Customer name"
                                required
                            />
                            <flux:input
                                wire:model="newCustomerPhone"
                                label="Phone"
                                placeholder="+255..."
                                required
                            />
                            <flux:input
                                wire:model="newCustomerEmail"
                                label="Email"
                                type="email"
                                placeholder="email@example.com"
                            />
                            <flux:input
                                wire:model="newCustomerAddress"
                                label="Address"
                                placeholder="Customer address"
                            />
                        </div>
                    </div>
                @endif
            </flux:card>

            {{-- Order Details --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">Order Details</flux:heading>

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
                            min="{{ date('Y-m-d') }}"
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

                    <flux:input
                        wire:model.live="discount"
                        type="number"
                        step="1"
                        min="0"
                        label="Discount"
                        placeholder="0.00"
                    />
                </div>

                <div class="mt-4">
                    <flux:textarea
                        wire:model="notes"
                        label="Notes"
                        placeholder="Additional notes about this order..."
                        rows="3"
                    />
                </div>
            </flux:card>

            {{-- Order Lines --}}
            <flux:card>
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <flux:heading size="lg">Order Lines</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="subtle" wire:click="toggleInventoryPicker" type="button">
                            <x-icon name="inventory_2" class="mr-1 size-4" />
                            Inventory Items
                        </flux:button>
                        <flux:button size="sm" variant="subtle" wire:click="addLine" type="button">
                            <x-icon name="add" class="mr-1 size-4" />
                            Add Item
                        </flux:button>
                    </div>
                </div>

                @if ($showInventoryPicker)
                    <div class="mb-5 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="mb-3 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                            <flux:input
                                wire:model.live.debounce.300ms="inventorySearch"
                                label="Inventory search"
                                placeholder="Search inventory by name or SKU"
                                icon="magnifying-glass"
                            />
                            @if ($showBranchSelector && !$isEdit && !$branch_id)
                                <p class="text-sm text-amber-600 dark:text-amber-300">Select a branch to list inventory.</p>
                            @endif
                        </div>

                        @if ($inventoryItems->isNotEmpty())
                            <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                @foreach ($inventoryItems as $item)
                                    @php
                                        $availableQty = (float) ($item->stock?->qty_on_hand ?? 0) - (float) ($item->stock?->qty_reserved ?? 0);
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click="addInventoryLine({{ $item->id }})"
                                        class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-left transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:border-indigo-700 dark:hover:bg-indigo-900/20"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $item->name }}</p>
                                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ $item->sku ?: 'No SKU' }} @if($item->unit) &bull; {{ $item->unit }} @endif
                                                </p>
                                            </div>
                                            <span class="shrink-0 font-mono text-sm text-zinc-900 dark:text-white">{{ number_format((float) $item->default_sell_price, 0) }}</span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between text-xs">
                                            <span class="text-zinc-500 dark:text-zinc-400">Available</span>
                                            <span class="{{ $availableQty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ rtrim(rtrim(number_format($availableQty, 2), '0'), '.') }}
                                            </span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <p class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                No inventory items found for this branch.
                            </p>
                        @endif

                        @error('inventorySearch')
                            <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                @php
                    $hasOrderTailor = (int) ($assigned_tailor_id ?? 0) > 0;
                @endphp

                <div class="space-y-6">
                    @foreach ($lines as $index => $line)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50" wire:key="line-{{ $index }}">
                            {{-- Line Header --}}
                            <div class="mb-4 flex items-start justify-between">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge size="sm">Item {{ $index + 1 }}</flux:badge>
                                    @if (! empty($line['inventory_item_id']))
                                        <flux:badge size="sm" color="lime">Inventory</flux:badge>
                                    @endif
                                </div>
                                @if (count($lines) > 1)
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
                                    />
                                    @if (! empty($line['sku']))
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">SKU: {{ $line['sku'] }}</p>
                                    @endif
                                    @error("lines.$index.inventory_item_id")
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
                                    wire:model.live="lines.{{ $index }}.qty"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    label="Qty"
                                    required
                                />
                                @error("lines.$index.qty")
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                                <flux:input
                                    wire:model.blur="lines.{{ $index }}.unit_price"
                                    type="number"
                                    step="1"
                                    min="0"
                                    label="Unit Price"
                                    required
                                />
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
                    @endforeach
                </div>

                {{-- Totals --}}
                <div class="mt-6 flex justify-end">
                    <div class="w-full max-w-xs space-y-2 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Subtotal</span>
                            <span class="font-mono text-zinc-900 dark:text-white">{{ number_format($subtotal, 0) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Discount</span>
                            <span class="font-mono text-red-600 dark:text-red-400">-{{ number_format($discount ?? 0, 0) }}</span>
                        </div>
                        <div class="border-t border-zinc-200 pt-2 dark:border-zinc-700">
                            <div class="flex justify-between text-lg font-semibold">
                                <span class="text-zinc-900 dark:text-white">Total</span>
                                <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ number_format($total, 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card>

            {{-- Order Expenses --}}
            <flux:card>
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <flux:heading size="lg">Order Expenses</flux:heading>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Auto-generated per assigned tailor</p>
                </div>

                @php
                    $selectedExpenseTailorIds = $hasOrderTailor
                        ? collect([(int) $assigned_tailor_id])->filter()
                        : collect($lines)
                            ->filter(fn ($line) => trim((string) ($line['item_name'] ?? '')) !== '')
                            ->pluck('assigned_tailor_id')
                            ->filter()
                            ->map(fn ($id) => (int) $id)
                            ->unique()
                            ->values();

                    $expensesLocked = $selectedExpenseTailorIds->isEmpty();
                @endphp

                <div class="space-y-4">
                    @foreach ($order_expenses as $expenseIndex => $expense)
                        @php
                            $expenseTailorId = (int) ($expense['tailor_id'] ?? 0);
                            $expenseTailorName = $expenseTailorId > 0
                                ? ($tailors->firstWhere('id', $expenseTailorId)?->name ?? 'Assigned tailor')
                                : 'No tailor selected';
                        @endphp
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50" wire:key="order-expense-{{ $expenseIndex }}">
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <flux:badge size="sm">Expense {{ $expenseIndex + 1 }}</flux:badge>
                                <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ $expenseTailorName }}</span>
                            </div>

                            <input type="hidden" wire:model="order_expenses.{{ $expenseIndex }}.tailor_id" />

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input
                                    wire:model.blur="order_expenses.{{ $expenseIndex }}.notes"
                                    label="Description"
                                    placeholder="e.g. Tailoring labor cost"
                                    :disabled="$expensesLocked"
                                />
                                <flux:input
                                    wire:model.live="order_expenses.{{ $expenseIndex }}.amount"
                                    type="number"
                                    step="1"
                                    min="0"
                                    label="Amount"
                                    placeholder="0.00"
                                    :disabled="$expensesLocked"
                                />
                            </div>

                            @error("order_expenses.$expenseIndex.tailor_id")
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                            @error("order_expenses.$expenseIndex.notes")
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                            @error("order_expenses.$expenseIndex.amount")
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                @if ($expensesLocked)
                    <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                        Select an order tailor or assign line tailor(s) to activate order expenses.
                    </p>
                @elseif (!$hasOrderTailor)
                    <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                        One expense line is generated per unique inline tailor assignment.
                    </p>
                @endif
                @error('order_expenses')
                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </flux:card>

            {{-- Deposit (create only, optional) --}}
            @if (!$isEdit)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Deposit</flux:heading>
                    <flux:text class="mb-4 block text-sm text-zinc-500 dark:text-zinc-400">
                        Optionally record a deposit paid with this order. It will be added to the order&apos;s payments.
                    </flux:text>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <flux:input
                            wire:model.live="deposit_amount"
                            type="number"
                            step="1"
                            min="0"
                            :max="$total"
                            label="Deposit amount"
                            placeholder="0.00"
                        />
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
                    </div>
                </flux:card>
            @endif

            {{-- Actions --}}
            <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                <flux:button variant="ghost" :href="route('orders.index')" wire:navigate>
                    <x-icon name="arrow_back" class="mr-1 size-4" />
                    Back to Orders
                </flux:button>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ $isEdit ? 'Update Order' : 'Create Order' }}
                    </span>
                    <span wire:loading>
                        <x-icon name="refresh" class="mr-1 size-4 animate-spin" />
                        Saving...
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:main>
</div>

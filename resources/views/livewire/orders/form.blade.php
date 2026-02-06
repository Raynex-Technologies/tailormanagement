<div>
    <flux:main class="p-6">
        {{-- Page Header --}}
        <div class="mb-6">
            <flux:breadcrumbs>
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
                    <flux:icon name="exclamation-circle" class="size-5 text-red-600 dark:text-red-400" />
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
                    <flux:icon name="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400" />
                    <div>
                        <flux:heading size="sm" class="text-amber-800 dark:text-amber-200">Branch Selection Required</flux:heading>
                        <p class="text-sm text-amber-700 dark:text-amber-300">
                            Select a branch below to create this order. Orders and customers will be assigned to the selected branch.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <form wire:submit="save" class="space-y-6">
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
                    <div class="relative">
                        <flux:input
                            wire:model.live.debounce.300ms="customerSearch"
                            wire:focus="showCustomerDropdown = true"
                            placeholder="Search customer by name or phone..."
                            icon="magnifying-glass"
                            :disabled="$customer_id !== null"
                            autocomplete="off"
                        />

                        @if ($customer_id)
                            <div class="mt-2 flex items-center gap-2">
                                <flux:badge color="green" size="sm">Selected</flux:badge>
                                <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $customerSearch }}</span>
                                <flux:button size="xs" variant="ghost" wire:click="$set('customer_id', null); $set('customerSearch', '')">
                                    Change
                                </flux:button>
                            </div>
                        @endif

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
                                            <span class="ml-2 text-sm text-zinc-500">{{ $customer->phone }}</span>
                                        </div>
                                        <span class="text-xs text-zinc-400">{{ $customer->code }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif ($showCustomerDropdown && strlen($customerSearch) >= 2 && count($customers) === 0)
                            <div class="absolute z-50 mt-1 w-full rounded-xl border border-zinc-200 bg-white p-4 text-center text-sm text-zinc-500 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                No customers found.
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 flex items-center gap-2">
                        <span class="text-sm text-zinc-500">or</span>
                        <flux:button size="sm" variant="subtle" wire:click="toggleNewCustomerForm">
                            <flux:icon name="plus" class="mr-1 size-4" />
                            Create New Customer
                        </flux:button>
                    </div>
                @else
                    {{-- New Customer Form --}}
                    <div class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="flex items-center justify-between">
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
                    <flux:input
                        wire:model="due_date"
                        type="date"
                        label="Due Date"
                        :min="date('Y-m-d')"
                    />

                    <flux:select wire:model="priority" label="Priority">
                        @foreach ($priorities as $p)
                            <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @can('orders.assign_tailor')
                        <flux:select wire:model="assigned_tailor_id" label="Assigned Tailor">
                            <flux:select.option value="">-- Select Tailor --</flux:select.option>
                            @foreach ($tailors as $tailor)
                                <flux:select.option value="{{ $tailor->id }}">{{ $tailor->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endcan

                    <flux:input
                        wire:model.live="discount"
                        type="number"
                        step="0.01"
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
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg">Order Lines</flux:heading>
                    <flux:button size="sm" variant="subtle" wire:click="addLine" type="button">
                        <flux:icon name="plus" class="mr-1 size-4" />
                        Add Item
                    </flux:button>
                </div>

                <div class="space-y-6">
                    @foreach ($lines as $index => $line)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50" wire:key="line-{{ $index }}">
                            {{-- Line Header --}}
                            <div class="mb-4 flex items-start justify-between">
                                <flux:badge size="sm">Item {{ $index + 1 }}</flux:badge>
                                @if (count($lines) > 1)
                                    <flux:button size="xs" variant="ghost" wire:click="removeLine({{ $index }})" type="button" title="Remove Item">
                                        <flux:icon name="trash" class="size-4 text-red-500" />
                                    </flux:button>
                                @endif
                            </div>

                            {{-- Line Details --}}
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                <div class="lg:col-span-2">
                                    <flux:input
                                        wire:model="lines.{{ $index }}.item_name"
                                        label="Item Name"
                                        placeholder="e.g. Men's Suit"
                                        required
                                    />
                                </div>
                                <flux:input
                                    wire:model.live="lines.{{ $index }}.qty"
                                    type="number"
                                    step="1"
                                    min="1"
                                    label="Qty"
                                    required
                                />
                                <flux:input
                                    wire:model.live="lines.{{ $index }}.unit_price"
                                    type="number"
                                    step="0.01"
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
                                <div class="mb-2 flex items-center justify-between">
                                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Measurements</label>
                                    <flux:button size="xs" variant="ghost" wire:click="addMeasurement({{ $index }})" type="button">
                                        <flux:icon name="plus" class="mr-1 size-3" />
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
                                                    <flux:icon name="x-mark" class="size-4 text-zinc-400" />
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

            {{-- Actions --}}
            <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:button variant="ghost" :href="route('orders.index')" wire:navigate>
                    <flux:icon name="arrow-left" class="mr-1 size-4" />
                    Back to Orders
                </flux:button>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>
                        <flux:icon name="check" class="mr-1 size-4" />
                        {{ $isEdit ? 'Update Order' : 'Create Order' }}
                    </span>
                    <span wire:loading>
                        <flux:icon name="arrow-path" class="mr-1 size-4 animate-spin" />
                        Saving...
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:main>
</div>

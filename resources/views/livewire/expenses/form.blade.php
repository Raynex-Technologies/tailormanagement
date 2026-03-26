<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('expenses.index') }}" wire:navigate>{{ __('Expenses') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $isEdit ? __('Edit') : __('New') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-circle">
            {{ session('error') }}
        </flux:callout>
    @endif

    @if ($isLinkedToCapital)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('This expense is linked to a capital allocation. Amount and capital allocation cannot be changed.') }}
        </flux:callout>
    @endif

    {{-- Branch Selection Banner for Global Admins --}}
    @if ($showBranchSelector && !$isEdit && !$branchId)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Select a branch below to create this expense.') }}
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
                        {{ __('The expense will be recorded in this branch.') }}
                    </flux:text>
                    @error('branchId')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
            </flux:card>
        @endif

        <flux:card class="mb-6">
            <flux:heading size="xl" class="mb-6">
                {{ $isEdit ? __('Edit Expense') : __('New Expense') }}
            </flux:heading>

            <div class="grid gap-6 sm:grid-cols-2">
                {{-- Expense Date --}}
                <div>
                    <flux:label for="expenseDate">{{ __('Date') }} *</flux:label>
                    <flux:input type="date" id="expenseDate" wire:model="expenseDate" />
                    @error('expenseDate')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Category --}}
                <div>
                    <flux:label for="expenseCategoryId">{{ __('Category') }}</flux:label>
                    <flux:select id="expenseCategoryId" wire:model.live="expenseCategoryId">
                        <flux:select.option value="">{{ __('-- No Category --') }}</flux:select.option>
                        @foreach ($categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('expenseCategoryId')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Subcategory --}}
                <div>
                    <flux:label for="expenseSubcategoryId">{{ __('Subcategory') }}</flux:label>
                    <flux:select
                        id="expenseSubcategoryId"
                        wire:model.live="expenseSubcategoryId"
                        wire:key="expense-subcategory-select-{{ $expenseCategoryId ?: 'none' }}"
                        :disabled="!$expenseCategoryId"
                    >
                        @if (!$expenseCategoryId)
                            <flux:select.option value="">{{ __('-- Select Category First --') }}</flux:select.option>
                        @elseif ($subcategories->isEmpty())
                            <flux:select.option value="">{{ __('-- No Subcategories Available --') }}</flux:select.option>
                        @else
                            <flux:select.option value="">{{ __('-- No Subcategory --') }}</flux:select.option>
                            @foreach ($subcategories as $subcategory)
                                <flux:select.option value="{{ $subcategory->id }}">{{ $subcategory->name }}</flux:select.option>
                            @endforeach
                        @endif
                    </flux:select>
                    @error('expenseSubcategoryId')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Supplier --}}
                <div class="relative z-20">
                    <flux:label for="supplierSearch">{{ __('Supplier') }}</flux:label>
                    <div class="relative">
                        <div class="relative">
                            <x-icon name="search" class="absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
                            <input
                                type="text"
                                id="supplierSearch"
                                wire:model.live.debounce.250ms="supplierSearch"
                                wire:focus="openSupplierDropdown"
                                wire:keydown.escape="closeSupplierDropdown"
                                placeholder="{{ $canSearchSuppliers ? __('Type supplier name...') : __('Select branch first...') }}"
                                autocomplete="off"
                                class="w-full rounded-lg border border-zinc-300 bg-white py-2.5 pl-10 pr-10 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:focus:border-indigo-400 {{ !$canSearchSuppliers ? 'cursor-not-allowed opacity-60' : '' }}"
                                {{ !$canSearchSuppliers ? 'disabled' : '' }}
                            />
                            @if ($supplierId || $supplierSearch)
                                <button
                                    type="button"
                                    wire:click="clearSupplierSelection"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                                >
                                    <x-icon name="close" class="size-5" />
                                </button>
                            @endif
                        </div>

                        @if ($showSupplierDropdown && $supplierResults->isNotEmpty())
                            <div
                                class="absolute z-50 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-zinc-200 bg-white shadow-xl shadow-zinc-950/10 dark:border-zinc-700 dark:bg-zinc-800 dark:shadow-black/30"
                                wire:click.outside="closeSupplierDropdown"
                            >
                                @foreach ($supplierResults as $supplier)
                                    <button
                                        type="button"
                                        wire:click="selectSupplier({{ $supplier->id }})"
                                        class="flex w-full items-center justify-between px-4 py-3 text-left transition hover:bg-indigo-50 dark:hover:bg-indigo-900/20"
                                    >
                                        <div>
                                            <p class="font-medium text-zinc-900 dark:text-white">{{ $supplier->name }}</p>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $supplier->phone ?: __('No phone') }}</p>
                                        </div>
                                        <x-icon name="check_circle" class="size-5 text-indigo-500" />
                                    </button>
                                @endforeach
                            </div>
                        @elseif ($showSupplierDropdown && filled(trim($supplierSearch)))
                            <div
                                class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 bg-white p-3 text-sm text-zinc-500 shadow-xl shadow-zinc-950/10 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:shadow-black/30"
                                wire:click.outside="closeSupplierDropdown"
                            >
                                {{ __('No suppliers found for this search.') }}
                            </div>
                        @endif
                    </div>

                    @if (!$canSearchSuppliers && $showBranchSelector && !$isEdit)
                        <flux:text class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            {{ __('Select a branch above to search suppliers.') }}
                        </flux:text>
                    @endif

                    @if ($isEdit && $preserveExistingVendor && !$supplierId && $vendor)
                        <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Current vendor on this record:') }} <span class="font-medium">{{ $vendor }}</span>
                        </flux:text>
                    @endif

                    @error('supplierId')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                    @error('vendor')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Amount --}}
                <div>
                    <flux:label for="amount">{{ __('Amount (TZS)') }} *</flux:label>
                    <flux:input
                        type="number"
                        id="amount"
                        wire:model="amount"
                        step="1"
                        min="1"
                        placeholder="0"
                        :disabled="$isLinkedToCapital"
                    />
                    @if ($isLinkedToCapital)
                        <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Cannot be modified for capital-linked expenses.') }}</flux:text>
                    @endif
                    @error('amount')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Reference --}}
                <div>
                    <flux:label for="reference">{{ __('Reference / Invoice No') }}</flux:label>
                    <flux:input id="reference" wire:model="reference" placeholder="INV-001, Receipt #123..." />
                    @error('reference')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Capital Allocation --}}
                @can('capital.view')
                <div>
                    <flux:label for="capitalAllocationId">{{ __('Link to Capital Allocation') }}</flux:label>
                    <flux:select
                        id="capitalAllocationId"
                        wire:model.blur="capitalAllocationId"
                        :disabled="$isLinkedToCapital"
                    >
                        <flux:select.option value="">{{ __('-- None --') }}</flux:select.option>
                        @foreach ($allocations as $allocation)
                            <flux:select.option value="{{ $allocation->id }}">
                                {{ $allocation->allocation_no }} ({{ $allocation->accountant?->name ?? 'N/A' }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($isLinkedToCapital)
                        <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Cannot be changed for linked expenses.') }}</flux:text>
                    @endif
                    @error('capitalAllocationId')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror

                    {{-- Available Balance Preview --}}
                    @if ($availableBalance !== null)
                        <div class="mt-2 rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-900/30">
                            <flux:text class="text-sm text-green-700 dark:text-green-300">
                                {{ __('Available Balance') }}: <strong class="font-mono">{{ money_tzs($availableBalance) }}</strong>
                            </flux:text>
                            @if ($amount && $amount > $availableBalance)
                                <flux:text class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    <x-icon name="warning" class="mr-1 inline size-4" />
                                    {{ __('Amount exceeds available balance!') }}
                                </flux:text>
                            @endif
                        </div>
                    @endif
                </div>
                @endcan
            </div>

            {{-- Note --}}
            <div class="mt-6">
                <flux:label for="note">{{ __('Note') }}</flux:label>
                <flux:textarea id="note" wire:model="note" rows="3" placeholder="Additional details about this expense..." />
                @error('note')
                    <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                @enderror
            </div>
        </flux:card>

        {{-- Actions --}}
        <div class="flex justify-end gap-3">
            <flux:button type="button" variant="ghost" :href="route('expenses.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                <x-icon name="check" class="mr-1 size-4" />
                {{ $isEdit ? __('Update Expense') : __('Create Expense') }}
            </flux:button>
        </div>
    </form>
</flux:main>

<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Customers') }}</flux:breadcrumbs.item>
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
                <flux:heading size="xl">{{ __('Customers') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Manage customer profiles and contacts.') }}</flux:text>
            </div>
            @if ($canCreate)
                <flux:button variant="primary" wire:click="openCreateModal">
                    <x-icon name="add" class="mr-1 size-4" />
                    {{ __('New Customer') }}
                </flux:button>
            @endif
        </div>
    </flux:card>

    {{-- Stats --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Total Customers') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ number_format($stats['total']) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text class="text-sm text-zinc-500">{{ __('Customers With Orders') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ number_format($stats['with_orders']) }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search code, name, phone, email...') }}"
                icon="magnifying-glass"
            />

            @if ($branches->isNotEmpty())
                <flux:select wire:model.live="branchFilter">
                    <flux:select.option value="">{{ __('All Branches') }}</flux:select.option>
                    @foreach ($branches as $branch)
                        <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="perPage">
                <flux:select.option value="15">15 {{ __('per page') }}</flux:select.option>
                <flux:select.option value="25">25 {{ __('per page') }}</flux:select.option>
                <flux:select.option value="50">50 {{ __('per page') }}</flux:select.option>
            </flux:select>

            <div class="flex items-end">
                <flux:button type="button" wire:click="clearFilters" variant="ghost" size="sm">
                    <x-icon name="close" class="mr-1 size-4" />
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Customers Table --}}
    <flux:card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <th class="px-4 py-3">{{ __('Code') }}</th>
                        <th class="px-4 py-3">{{ __('Name') }}</th>
                        <th class="px-4 py-3">{{ __('Phone') }}</th>
                        <th class="px-4 py-3">{{ __('Email') }}</th>
                        <th class="px-4 py-3">{{ __('Branch') }}</th>
                        <th class="px-4 py-3">{{ __('Orders') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($customers as $customer)
                        <tr class="text-sm text-zinc-900 hover:bg-zinc-50 dark:text-zinc-100 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3 font-mono text-xs">{{ $customer->code }}</td>
                            <td class="px-4 py-3 font-medium">{{ $customer->name }}</td>
                            <td class="px-4 py-3">{{ $customer->phone ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $customer->email ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $customer->branch?->name ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <flux:badge color="zinc" size="sm">{{ $customer->orders_count }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($canView)
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button size="xs" variant="ghost" :href="route('customers.show', $customer)" wire:navigate>
                                            <x-icon name="visibility" class="size-4" />
                                        </flux:button>
                                        @if ($canUpdate)
                                        <flux:button size="xs" variant="ghost" wire:click="openEditModal({{ $customer->id }})">
                                            <x-icon name="edit" class="size-4" />
                                        </flux:button>
                                        @endif
                                        @if ($canDelete)
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            wire:click="delete({{ $customer->id }})"
                                            wire:confirm="{{ __('Delete this customer? This cannot be undone.') }}"
                                        >
                                            <x-icon name="delete" class="size-4 text-red-500" />
                                        </flux:button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No customers found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                {{ $customers->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Create/Edit Modal --}}
    <flux:modal wire:model="showFormModal" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit Customer') : __('New Customer') }}
            </flux:heading>

            <form wire:submit="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    @if ($showBranchSelector && ! $editingId)
                        <div class="sm:col-span-2">
                            <flux:label for="branchId">{{ __('Branch') }} *</flux:label>
                            <flux:select id="branchId" wire:model.live="branchId">
                                <flux:select.option value="">{{ __('-- Select Branch --') }}</flux:select.option>
                                @foreach ($branches as $branch)
                                    <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('branchId')
                                <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                            @enderror
                        </div>
                    @endif

                    <div>
                        <flux:label for="name">{{ __('Name') }} *</flux:label>
                        <flux:input id="name" wire:model="name" placeholder="{{ __('Customer name') }}" />
                        @error('name')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div>
                        <flux:label for="phone">{{ __('Phone') }}</flux:label>
                        <flux:input id="phone" wire:model="phone" placeholder="{{ __('Phone number') }}" />
                        @error('phone')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div>
                        <flux:label for="email">{{ __('Email') }}</flux:label>
                        <flux:input type="email" id="email" wire:model="email" placeholder="{{ __('email@example.com') }}" />
                        @error('email')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div>
                        <flux:label for="dob">{{ __('Date of Birth') }}</flux:label>
                        <flux:input type="date" id="dob" wire:model="dob" />
                        @error('dob')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <flux:label for="address">{{ __('Address') }}</flux:label>
                        <flux:textarea id="address" wire:model="address" rows="2" />
                        @error('address')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <flux:label for="notes">{{ __('Notes') }}</flux:label>
                        <flux:textarea id="notes" wire:model="notes" rows="3" />
                        @error('notes')
                            <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>
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

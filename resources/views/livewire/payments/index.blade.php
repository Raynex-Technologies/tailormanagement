<flux:main class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
        <flux:breadcrumbs.item :href="route('orders.index')" wire:navigate>{{ __('Orders') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Payments') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

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

    <flux:card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('All Payments') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Track all received payments and adjust amounts when required.') }}</flux:text>
            </div>
        </div>
    </flux:card>

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Total Amount') }}</flux:text>
            <flux:heading size="lg" class="mt-1 font-mono text-emerald-600 dark:text-emerald-400">
                {{ money_tzs($stats['total_amount']) }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Total Payments') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ number_format($stats['total_count']) }}
            </flux:heading>
        </flux:card>
    </div>

    <flux:card>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @if ($branches->isNotEmpty())
                <div class="xl:col-span-1">
                    <flux:label for="branchFilter">{{ __('Branch') }}</flux:label>
                    <flux:select id="branchFilter" wire:model.live="branchFilter">
                        <flux:select.option value="">{{ __('All Branches') }}</flux:select.option>
                        @foreach ($branches as $branch)
                            <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            <div class="{{ $branches->isNotEmpty() ? 'xl:col-span-2' : 'xl:col-span-3' }}">
                <flux:label for="search">{{ __('Order Number') }}</flux:label>
                <flux:input
                    id="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by order no..."
                    icon="magnifying-glass"
                />
            </div>

            <div class="xl:col-span-1">
                <flux:label for="dateFrom">{{ __('From') }}</flux:label>
                <flux:input id="dateFrom" type="date" wire:model.live="dateFrom" />
            </div>

            <div class="xl:col-span-1">
                <flux:label for="dateTo">{{ __('To') }}</flux:label>
                <flux:input id="dateTo" type="date" wire:model.live="dateTo" />
            </div>

            <div class="flex items-end gap-2 xl:col-span-1">
                <flux:select wire:model.live="perPage" class="w-24">
                    <flux:select.option value="15">15</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>
                <flux:button variant="ghost" wire:click="clearFilters">
                    <x-icon name="close" class="size-4" />
                </flux:button>
            </div>
        </div>
    </flux:card>

    <flux:card>
        @if ($payments->isEmpty())
            <div class="py-12 text-center">
                <x-icon name="payments" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-4">{{ __('No payments found') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Try changing the selected filters.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">{{ __('Date') }}</th>
                            <th class="px-4 py-3">{{ __('Branch') }}</th>
                            <th class="px-4 py-3">{{ __('Order No') }}</th>
                            <th class="px-4 py-3">{{ __('Customer') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                            <th class="px-4 py-3">{{ __('Method') }}</th>
                            <th class="px-4 py-3">{{ __('Reference') }}</th>
                            <th class="px-4 py-3">{{ __('Received By') }}</th>
                            @if ($canEditAmounts)
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($payments as $payment)
                            <tr class="text-sm text-zinc-900 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $payment->paid_at?->format('M d, Y H:i') ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $payment->branch?->name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($payment->order)
                                        <a href="{{ route('orders.show', $payment->order) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                            {{ $payment->order->order_no }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $payment->order?->customer?->name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">
                                    {{ money_tzs($payment->amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $payment->paymentMethod?->name ?? __('Default') }}
                                </td>
                                <td class="px-4 py-3 text-zinc-500">
                                    {{ $payment->reference ?: '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $payment->receiver?->name ?? '-' }}
                                </td>
                                @if ($canEditAmounts)
                                    <td class="px-4 py-3 text-right">
                                        <flux:button size="xs" variant="ghost" wire:click="openEditAmountModal({{ $payment->id }})">
                                            <x-icon name="edit" class="mr-1 size-4" />
                                            {{ __('Edit Amount') }}
                                        </flux:button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    {{ $payments->links() }}
                </div>
            @endif
        @endif
    </flux:card>

    <flux:modal wire:model="showEditAmountModal" class="w-full max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Edit Payment Amount') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Only superadmin can update payment amount.') }}</flux:text>
            </div>

            <form wire:submit="updateAmount" class="space-y-4">
                <div>
                    <flux:label for="editingAmount">{{ __('Amount') }}</flux:label>
                    <flux:input
                        id="editingAmount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        wire:model="editingAmount"
                    />
                    @error('editingAmount')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showEditAmountModal', false)">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit">
                        {{ __('Save Changes') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</flux:main>

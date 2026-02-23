<div>
    <flux:main class="p-6">
        {{-- Page Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Orders Management') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                    {{ __('View and manage tailoring orders.') }}
                </flux:text>
            </div>

            @can('orders.create')
                <flux:button icon="plus" :href="route('orders.create')" wire:navigate>
                    {{ __('New Order') }}
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
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <flux:input
                    wire:model.blur="search"
                    placeholder="Search order, customer..."
                    icon="magnifying-glass"
                />

                <flux:select wire:model.blur="statusFilter">
                    <flux:select.option value="">All Status</flux:select.option>
                    @foreach ($statuses as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.blur="tailorFilter">
                    <flux:select.option value="">All Tailors</flux:select.option>
                    @foreach ($tailors as $id => $name)
                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model.blur="dateFrom"
                    type="date"
                    placeholder="From date"
                />

                <flux:input
                    wire:model.blur="dateTo"
                    type="date"
                    placeholder="To date"
                />

                <div class="flex items-center gap-2">
                    <flux:select wire:model.blur="perPage" class="flex-1">
                        <flux:select.option value="15">15</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                    </flux:select>

                    <flux:button size="sm" variant="ghost" wire:click="clearFilters" title="Clear Filters">
                        <x-icon name="close" class="size-4" />
                    </flux:button>
                </div>
            </div>
        </flux:card>

        {{-- Orders Table --}}
        <flux:card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-sm font-semibold text-zinc-900 dark:text-white">
                            <th class="px-4 py-3">{{ __('Order No') }}</th>
                            <th class="px-4 py-3">{{ __('Customer') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                            <th class="px-4 py-3">{{ __('Due Date') }}</th>
                            @if ($canViewFinancials)
                                <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Payment') }}</th>
                            @endif
                            <th class="px-4 py-3">{{ __('Tailor') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($orders as $order)
                            @php
                                $statusColor = match($order->status) {
                                    \App\Enums\OrderStatus::New => 'blue',
                                    \App\Enums\OrderStatus::InProgress => 'amber',
                                    \App\Enums\OrderStatus::Ready => 'purple',
                                    \App\Enums\OrderStatus::Delivered => 'green',
                                    \App\Enums\OrderStatus::Completed => 'green',
                                    \App\Enums\OrderStatus::Cancelled => 'red',
                                    default => 'zinc',
                                };
                                $paymentColor = match($order->payment_status) {
                                    \App\Enums\PaymentStatus::Paid => 'green',
                                    \App\Enums\PaymentStatus::Partial => 'amber',
                                    \App\Enums\PaymentStatus::Unpaid => 'red',
                                    default => 'zinc',
                                };
                            @endphp
                            <tr class="text-sm text-zinc-700 dark:text-zinc-300" wire:key="order-{{ $order->id }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('orders.show', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                                        {{ $order->order_no }}
                                    </a>
                                    @if ($order->isOverdue())
                                        <flux:badge size="sm" color="red" class="ml-1">Overdue</flux:badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ $order->customer?->name ?? 'N/A' }}</span>
                                        <span class="text-xs text-zinc-500">{{ $order->customer?->phone }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <flux:badge color="{{ $statusColor }}">
                                        {{ $order->status->label() }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $order->due_date?->format('M d, Y') ?? '—' }}
                                </td>
                                @if ($canViewFinancials)
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ number_format($order->total, 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <flux:badge color="{{ $paymentColor }}">
                                            {{ $order->payment_status->label() }}
                                        </flux:badge>
                                    </td>
                                @endif
                                <td class="px-4 py-3">
                                    {{ $order->assignedTailor?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="eye"
                                            :href="route('orders.show', $order)"
                                            wire:navigate
                                            title="View"
                                        />
                                        @can('orders.update')
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="pencil"
                                                :href="route('orders.edit', $order)"
                                                wire:navigate
                                                title="Edit"
                                            />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canViewFinancials ? 8 : 6 }}" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <x-icon name="description" class="size-12 text-zinc-300 dark:text-zinc-600" />
                                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                                            {{ __('No orders found.') }}
                                        </flux:text>
                                        @can('orders.create')
                                            <flux:button size="sm" :href="route('orders.create')" wire:navigate>
                                                {{ __('Create your first order') }}
                                            </flux:button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="mt-4 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $orders->links() }}
                </div>
            @endif
        </flux:card>
    </flux:main>
</div>

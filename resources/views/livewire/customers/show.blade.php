<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('customers.index') }}" wire:navigate>{{ __('Customers') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $customer->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Header --}}
    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ $customer->name }}</flux:heading>
                    <flux:badge color="zinc" size="sm">{{ $customer->code }}</flux:badge>
                </div>
                <flux:text class="mt-1 text-zinc-500">
                    {{ $customer->phone ?: __('No phone') }}
                    @if ($customer->email)
                        <span class="mx-1 text-zinc-400">|</span>
                        {{ $customer->email }}
                    @endif
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:button type="button" variant="ghost" :href="route('customers.index')" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
                @if ($canManage)
                    <flux:button type="button" variant="subtle" :href="route('customers.index')" wire:navigate>
                        <x-icon name="edit" class="mr-1 size-4" />
                        {{ __('Manage') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:card>

    {{-- Summary --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Total Orders') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ number_format($stats['total_orders']) }}</flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Completed Orders') }}</flux:text>
            <flux:heading size="lg" class="mt-1">{{ number_format($stats['completed_orders']) }}</flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Open Order Value') }}</flux:text>
            <flux:heading size="lg" class="mt-1 font-mono text-amber-600 dark:text-amber-400">{{ money_tzs($stats['open_order_value']) }}</flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Lifetime Value') }}</flux:text>
            <flux:heading size="lg" class="mt-1 font-mono text-green-600 dark:text-green-400">{{ money_tzs($stats['lifetime_value']) }}</flux:heading>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Customer Details --}}
        <div class="lg:col-span-1 space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Customer Details') }}</flux:heading>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Code') }}</dt>
                        <dd class="font-mono">{{ $customer->code }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Phone') }}</dt>
                        <dd>{{ $customer->phone ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Email') }}</dt>
                        <dd class="max-w-[200px] truncate">{{ $customer->email ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Date of Birth') }}</dt>
                        <dd>{{ $customer->dob?->format('M d, Y') ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Branch') }}</dt>
                        <dd>{{ $customer->branch?->name ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Created') }}</dt>
                        <dd>{{ $customer->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                </dl>

                @if ($customer->address)
                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:text class="mb-1 text-sm text-zinc-500">{{ __('Address') }}</flux:text>
                        <flux:text class="text-sm whitespace-pre-wrap">{{ $customer->address }}</flux:text>
                    </div>
                @endif

                @if ($customer->notes)
                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:text class="mb-1 text-sm text-zinc-500">{{ __('Notes') }}</flux:text>
                        <flux:text class="text-sm whitespace-pre-wrap">{{ $customer->notes }}</flux:text>
                    </div>
                @endif
            </flux:card>
        </div>

        {{-- Order History --}}
        <div class="lg:col-span-2">
            <flux:card>
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Order History') }}</flux:heading>

                    <div class="w-36">
                        <flux:select wire:model.live="ordersPerPage">
                            <flux:select.option value="10">10 {{ __('per page') }}</flux:select.option>
                            <flux:select.option value="25">25 {{ __('per page') }}</flux:select.option>
                            <flux:select.option value="50">50 {{ __('per page') }}</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                @if ($orders->isEmpty())
                    <div class="py-10 text-center text-zinc-500">
                        {{ __('No order history for this customer yet.') }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead>
                                <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    <th class="px-4 py-3">{{ __('Order No') }}</th>
                                    <th class="px-4 py-3">{{ __('Order Date') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3">{{ __('Payment') }}</th>
                                    <th class="px-4 py-3">{{ __('Total') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($orders as $order)
                                    <tr class="text-sm text-zinc-900 dark:text-zinc-100">
                                        <td class="px-4 py-3 font-medium">{{ $order->order_no }}</td>
                                        <td class="px-4 py-3">{{ $order->order_date?->format('M d, Y') ?? $order->created_at->format('M d, Y') }}</td>
                                        <td class="px-4 py-3">
                                            <flux:badge color="{{ $order->status->color() }}" size="sm">
                                                {{ $order->status->label() }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:badge color="{{ $order->payment_status->color() }}" size="sm">
                                                {{ $order->payment_status->label() }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-3 font-mono">{{ money_tzs($order->total) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <flux:button size="xs" variant="ghost" :href="route('orders.show', $order)" wire:navigate>
                                                <x-icon name="visibility" class="size-4" />
                                            </flux:button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        {{ $orders->links() }}
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</flux:main>


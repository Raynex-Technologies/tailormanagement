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

    {{-- Measurement Portfolio --}}
    <flux:card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('Measurements') }}</flux:heading>
                    @if ($currentMeasurementProfile)
                        <flux:badge color="green" size="sm">{{ __('Revision :revision', ['revision' => $currentMeasurementProfile->revision]) }}</flux:badge>
                    @endif
                </div>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Saved body measurements and immutable revision history.') }}</flux:text>
            </div>
            @if ($canManage)
                <flux:button type="button" variant="subtle" :href="route('customers.measurements.record', $customer)" wire:navigate>
                    {{ $currentMeasurementProfile ? __('Update Measurements') : __('Record Measurements') }}
                </flux:button>
            @endif
        </div>

        @if (! $currentMeasurementProfile)
            <div class="mt-5 rounded-xl border border-dashed border-zinc-300 px-5 py-8 text-center dark:border-white/15">
                <flux:text class="font-medium">{{ __('No saved measurements yet.') }}</flux:text>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Record a profile when this customer is measured in person.') }}</flux:text>
            </div>
        @else
            <div class="mt-5 grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]">
                <div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($currentMeasurementProfile->orderedValues() as $value)
                            <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/10">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium">{{ $value->field_label_snapshot ?: $value->field?->name ?: __('Archived measurement') }}</div>
                                        <div class="mt-0.5 truncate font-mono text-xs text-zinc-500">{{ $value->field_code_snapshot ?: $value->field?->code ?: '—' }}</div>
                                    </div>
                                    @if (! ($value->field?->is_active ?? false))
                                        <flux:badge color="zinc" size="sm">{{ __('Archived') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="mt-2 text-lg font-semibold tabular-nums">{{ $value->value }} <span class="text-xs font-normal text-zinc-500">{{ $value->unit }}</span></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-xs text-zinc-500">
                        <span>{{ __('Measured: :date', ['date' => $currentMeasurementProfile->measured_at?->format('M d, Y') ?: __('Not recorded')]) }}</span>
                        <span>{{ __('Recorded by: :name', ['name' => $currentMeasurementProfile->recordedBy?->name ?: __('Former or unavailable user')]) }}</span>
                    </div>
                    @if ($currentMeasurementProfile->notes)
                        <p class="mt-3 whitespace-pre-wrap text-sm text-zinc-600 dark:text-zinc-300">{{ $currentMeasurementProfile->notes }}</p>
                    @endif
                </div>

                <div class="border-t border-zinc-200 pt-5 dark:border-white/10 xl:border-l xl:border-t-0 xl:pl-6 xl:pt-0">
                    <div class="mb-3 text-sm font-medium">{{ __('Revision History') }}</div>
                    <div class="space-y-2">
                        @foreach ($measurementHistory as $revision)
                            <a href="{{ route('customers.measurements.show', [$customer, $revision]) }}" wire:navigate class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-green-500 dark:hover:bg-white/5">
                                <span>
                                    <span class="font-medium">{{ __('Revision :revision', ['revision' => $revision->revision]) }}</span>
                                    <span class="ml-1 text-xs text-zinc-500">{{ $revision->measured_at?->format('M d, Y') ?: '—' }}</span>
                                </span>
                                @if ($revision->is_current)
                                    <flux:badge color="green" size="sm">{{ __('Current') }}</flux:badge>
                                @else
                                    <span class="text-xs text-zinc-500">{{ __('View') }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                    @if ($measurementHistory->count() === 8)
                        <p class="mt-3 text-xs text-zinc-500">{{ __('Showing the 8 most recent revisions.') }}</p>
                    @endif
                </div>
            </div>
        @endif
    </flux:card>

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

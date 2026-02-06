<div>
    <flux:main class="p-6">
        {{-- Page Header --}}
        <div class="mb-6">
            <flux:heading size="xl">{{ __('Stock Requests Inbox') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                {{ __('Review and fulfill stock requests from orders.') }}
            </flux:text>
        </div>

        {{-- Status Tabs --}}
        <div class="mb-6 flex flex-wrap gap-2">
            <flux:button
                wire:click="$set('statusFilter', '')"
                :variant="$statusFilter === '' ? 'primary' : 'ghost'"
                size="sm"
            >
                All
            </flux:button>
            <flux:button
                wire:click="$set('statusFilter', 'requested')"
                :variant="$statusFilter === 'requested' ? 'primary' : 'ghost'"
                size="sm"
            >
                Requested
                @if ($statusCounts['requested'] > 0)
                    <flux:badge color="blue" size="sm" class="ml-1">{{ $statusCounts['requested'] }}</flux:badge>
                @endif
            </flux:button>
            <flux:button
                wire:click="$set('statusFilter', 'approved')"
                :variant="$statusFilter === 'approved' ? 'primary' : 'ghost'"
                size="sm"
            >
                Approved
                @if ($statusCounts['approved'] > 0)
                    <flux:badge color="amber" size="sm" class="ml-1">{{ $statusCounts['approved'] }}</flux:badge>
                @endif
            </flux:button>
            <flux:button
                wire:click="$set('statusFilter', 'fulfilled')"
                :variant="$statusFilter === 'fulfilled' ? 'primary' : 'ghost'"
                size="sm"
            >
                Fulfilled
            </flux:button>
            <flux:button
                wire:click="$set('statusFilter', 'declined')"
                :variant="$statusFilter === 'declined' ? 'primary' : 'ghost'"
                size="sm"
            >
                Declined
            </flux:button>
        </div>

        {{-- Filters --}}
        <flux:card class="mb-6">
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by order number, customer, or requester..."
                        icon="magnifying-glass"
                    />
                </div>
                <flux:select wire:model.live="perPage" class="w-24">
                    <flux:select.option value="15">15</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>
                <flux:button size="sm" variant="ghost" wire:click="clearFilters" title="Clear Filters">
                    <flux:icon name="x-mark" class="size-4" />
                </flux:button>
            </div>
        </flux:card>

        {{-- Requests Table --}}
        <flux:card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-sm font-semibold text-zinc-900 dark:text-white">
                            <th class="px-4 py-3">{{ __('Order') }}</th>
                            <th class="px-4 py-3">{{ __('Customer') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Items') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                            <th class="px-4 py-3">{{ __('Requested By') }}</th>
                            <th class="px-4 py-3">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($requests as $request)
                            @php
                                $statusColor = $request->status->color();
                            @endphp
                            <tr class="text-sm text-zinc-700 dark:text-zinc-300" wire:key="request-{{ $request->id }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('orders.show', $request->order) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                                        {{ $request->order?->order_no ?? 'N/A' }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $request->order?->customer?->name ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <flux:badge size="sm">{{ $request->items->count() }}</flux:badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <flux:badge color="{{ $statusColor }}">
                                        {{ $request->status->label() }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $request->requester?->name ?? 'Unknown' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $request->created_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="eye"
                                        :href="route('store.stock-requests.show', $request)"
                                        wire:navigate
                                        title="View / Review"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <flux:icon name="inbox-stack" class="size-12 text-zinc-300 dark:text-zinc-600" />
                                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                                            {{ __('No stock requests found.') }}
                                        </flux:text>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($requests->hasPages())
                <div class="mt-4 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $requests->links() }}
                </div>
            @endif
        </flux:card>
    </flux:main>
</div>

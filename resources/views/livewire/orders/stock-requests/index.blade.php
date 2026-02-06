<div>
    <flux:main class="p-6">
        {{-- Page Header --}}
        <div class="mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('orders.index')" wire:navigate>Orders</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('orders.show', $order)" wire:navigate>{{ $order->order_no }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Stock Requests</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="mt-4 flex items-center justify-between">
                <div>
                    <flux:heading size="xl">Stock Requests</flux:heading>
                    <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                        Order: {{ $order->order_no }} • Customer: {{ $order->customer?->name }}
                    </flux:text>
                </div>

                @if ($canCreate)
                    <flux:button wire:click="openNewRequestModal">
                        <flux:icon name="plus" class="mr-1 size-4" />
                        New Request
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
                {{ session('success') }}
            </div>
        @endif

        {{-- Requests List --}}
        <div class="space-y-4">
            @forelse ($requests as $request)
                @php
                    $statusColor = $request->status->color();
                @endphp
                <flux:card wire:key="request-{{ $request->id }}">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <flux:badge color="{{ $statusColor }}">
                                    {{ $request->status->label() }}
                                </flux:badge>
                                <span class="text-sm text-zinc-500">
                                    Requested by {{ $request->requester?->name ?? 'Unknown' }}
                                </span>
                                <span class="text-sm text-zinc-400">
                                    {{ $request->created_at->format('M d, Y H:i') }}
                                </span>
                            </div>

                            @if ($request->note)
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $request->note }}</p>
                            @endif

                            @if ($request->handler)
                                <p class="mt-1 text-sm text-zinc-500">
                                    Handled by {{ $request->handler->name }}
                                    @if ($request->handler_note)
                                        - {{ $request->handler_note }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="viewRequest({{ $request->id }})"
                        >
                            <flux:icon name="{{ $viewingRequestId === $request->id ? 'chevron-up' : 'chevron-down' }}" class="size-4" />
                        </flux:button>
                    </div>

                    {{-- Request Items (Expandable) --}}
                    @if ($viewingRequestId === $request->id)
                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-zinc-600 dark:text-zinc-400">
                                        <th class="pb-2">Item</th>
                                        <th class="pb-2 text-center">On Hand</th>
                                        <th class="pb-2 text-center">Requested</th>
                                        <th class="pb-2 text-center">Approved</th>
                                        <th class="pb-2 text-center">Issued</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($request->items as $item)
                                        <tr>
                                            <td class="py-2">
                                                <span class="font-medium text-zinc-900 dark:text-white">
                                                    {{ $item->inventoryItem?->name ?? 'Unknown' }}
                                                </span>
                                                @if ($item->note)
                                                    <span class="ml-2 text-xs text-zinc-500">({{ $item->note }})</span>
                                                @endif
                                            </td>
                                            <td class="py-2 text-center">
                                                {{ number_format($item->inventoryItem?->stock?->qty_on_hand ?? 0, 0) }}
                                            </td>
                                            <td class="py-2 text-center">{{ number_format($item->qty_requested, 0) }}</td>
                                            <td class="py-2 text-center">
                                                {{ $item->qty_approved !== null ? number_format($item->qty_approved, 0) : '—' }}
                                            </td>
                                            <td class="py-2 text-center">
                                                @if ($item->qty_issued > 0)
                                                    <span class="text-green-600 dark:text-green-400">
                                                        {{ number_format($item->qty_issued, 0) }}
                                                    </span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </flux:card>
            @empty
                <flux:card>
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <flux:icon name="inbox" class="size-12 text-zinc-300 dark:text-zinc-600" />
                        <flux:heading size="lg" class="mt-4">No Stock Requests</flux:heading>
                        <flux:text class="mt-1 text-zinc-500">
                            No stock requests have been made for this order yet.
                        </flux:text>
                        @if ($canCreate)
                            <flux:button class="mt-4" wire:click="openNewRequestModal">
                                <flux:icon name="plus" class="mr-1 size-4" />
                                Create First Request
                            </flux:button>
                        @endif
                    </div>
                </flux:card>
            @endforelse
        </div>

        {{-- Back Button --}}
        <div class="mt-6">
            <flux:button variant="ghost" :href="route('orders.show', $order)" wire:navigate>
                <flux:icon name="arrow-left" class="mr-1 size-4" />
                Back to Order
            </flux:button>
        </div>

        {{-- New Request Modal --}}
        <flux:modal wire:model="showNewRequestModal" class="max-w-2xl">
            <div class="space-y-4">
                <flux:heading size="lg">New Stock Request</flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    Request inventory items for order {{ $order->order_no }}.
                </flux:text>

                {{-- Error Messages --}}
                @if ($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                {{-- Request Items --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Items</label>
                        <flux:button size="xs" variant="ghost" wire:click="addRequestItem" type="button">
                            <flux:icon name="plus" class="mr-1 size-3" />
                            Add Item
                        </flux:button>
                    </div>

                    @foreach ($requestItems as $index => $item)
                        <div class="flex items-start gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50" wire:key="request-item-{{ $index }}">
                            <div class="flex-1 space-y-2">
                                {{-- Item Search/Select --}}
                                @if (empty($item['inventory_item_id']))
                                    <div class="relative">
                                        <flux:input
                                            wire:model.live.debounce.300ms="itemSearch"
                                            placeholder="Search inventory items..."
                                            icon="magnifying-glass"
                                        />
                                        @if (strlen($itemSearch) >= 2 && count($inventoryItems) > 0)
                                            <div class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                                @foreach ($inventoryItems as $invItem)
                                                    <button
                                                        type="button"
                                                        wire:click="selectItem({{ $index }}, {{ $invItem->id }})"
                                                        class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-700"
                                                    >
                                                        <div>
                                                            <span class="font-medium">{{ $invItem->name }}</span>
                                                            <span class="ml-2 text-xs text-zinc-500">{{ $invItem->sku }}</span>
                                                        </div>
                                                        <span class="text-sm text-zinc-500">
                                                            Stock: {{ number_format($invItem->stock?->qty_on_hand ?? 0, 0) }}
                                                        </span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="flex items-center gap-2">
                                        <flux:badge color="blue">{{ $item['inventory_item_name'] }}</flux:badge>
                                        <button
                                            type="button"
                                            wire:click="$set('requestItems.{{ $index }}.inventory_item_id', null)"
                                            class="text-zinc-400 hover:text-zinc-600"
                                        >
                                            <flux:icon name="x-mark" class="size-4" />
                                        </button>
                                    </div>
                                @endif

                                {{-- Quantity --}}
                                <div class="flex items-center gap-2">
                                    <flux:input
                                        wire:model="requestItems.{{ $index }}.qty_requested"
                                        type="number"
                                        min="1"
                                        step="1"
                                        placeholder="Qty"
                                        class="w-24"
                                    />
                                    <flux:input
                                        wire:model="requestItems.{{ $index }}.note"
                                        placeholder="Note (optional)"
                                        class="flex-1"
                                    />
                                </div>
                            </div>

                            @if (count($requestItems) > 1)
                                <flux:button size="xs" variant="ghost" wire:click="removeRequestItem({{ $index }})" type="button">
                                    <flux:icon name="trash" class="size-4 text-red-500" />
                                </flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Request Note --}}
                <flux:textarea
                    wire:model="requestNote"
                    label="Request Note (optional)"
                    placeholder="Any additional notes for this request..."
                    rows="2"
                />

                {{-- Actions --}}
                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showNewRequestModal', false)">
                        Cancel
                    </flux:button>
                    <flux:button variant="primary" wire:click="createRequest">
                        Submit Request
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </flux:main>
</div>

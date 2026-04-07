<div>
    <flux:main class="p-0">
        {{-- Page Header --}}
        <div class="mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
                <flux:breadcrumbs.item :href="route('store.stock-requests.index')" wire:navigate>Stock Requests</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Request #{{ $stockRequest->id }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
                {{ session('success') }}
            </div>
        @endif

        {{-- Error Messages --}}
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                @foreach ($errors->all() as $error)
                    <p class="text-red-700 dark:text-red-400">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Request Header --}}
        <flux:card class="mb-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <flux:heading size="xl">Stock Request #{{ $stockRequest->id }}</flux:heading>
                        @php
                            $statusColor = $stockRequest->status->color();
                        @endphp
                        <flux:badge color="{{ $statusColor }}" size="lg">
                            {{ $stockRequest->status->label() }}
                        </flux:badge>
                    </div>
                    <flux:text class="mt-2">
                        Order: <a href="{{ route('orders.show', $stockRequest->order) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                            {{ $stockRequest->order?->order_no }}
                        </a>
                        â€¢ Customer: <span class="font-medium">{{ $stockRequest->order?->customer?->name ?? 'N/A' }}</span>
                    </flux:text>
                </div>

                {{-- Actions --}}
                <div class="flex flex-wrap items-center gap-2">
                    @if ($canReview && $stockRequest->canBeReviewed())
                        <flux:button wire:click="openReviewModal">
                            <x-icon name="assignment_turned_in" class="mr-1 size-4" />
                            Review
                        </flux:button>
                    @endif

                    @if ($canFulfill && $stockRequest->canBeFulfilled())
                        <flux:button variant="primary" wire:click="openFulfillModal">
                            <x-icon name="download" class="mr-1 size-4" />
                            Fulfill / Issue
                        </flux:button>
                    @endif
                </div>
            </div>
        </flux:card>

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Request Items --}}
            <div class="xl:col-span-2">
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Requested Items</flux:heading>

                    <div class="overflow-x-auto custom-scrollbar-light">
                        <table class="w-full min-w-[620px] text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 text-left dark:border-zinc-700">
                                    <th class="pb-3 font-medium text-zinc-600 dark:text-zinc-400">Item</th>
                                    <th class="pb-3 text-center font-medium text-zinc-600 dark:text-zinc-400">On Hand</th>
                                    <th class="pb-3 text-center font-medium text-zinc-600 dark:text-zinc-400">Requested</th>
                                    <th class="pb-3 text-center font-medium text-zinc-600 dark:text-zinc-400">Approved</th>
                                    <th class="pb-3 text-center font-medium text-zinc-600 dark:text-zinc-400">Issued</th>
                                    <th class="pb-3 text-center font-medium text-zinc-600 dark:text-zinc-400">Remaining</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($stockRequest->items as $item)
                                    <tr>
                                        <td class="py-3">
                                            <div>
                                                <span class="font-medium text-zinc-900 dark:text-white">
                                                    {{ $item->inventoryItem?->name ?? 'Unknown' }}
                                                </span>
                                                @if ($item->inventoryItem?->sku)
                                                    <span class="ml-1 text-xs text-zinc-500">({{ $item->inventoryItem->sku }})</span>
                                                @endif
                                            </div>
                                            @if ($item->note)
                                                <p class="mt-1 text-xs text-zinc-500">{{ $item->note }}</p>
                                            @endif
                                        </td>
                                        <td class="py-3 text-center">
                                            @php
                                                $onHand = $item->inventoryItem?->stock?->qty_on_hand ?? 0;
                                                $isLow = $onHand < ($item->qty_requested ?? 0);
                                            @endphp
                                            <span class="{{ $isLow ? 'text-red-600 dark:text-red-400' : '' }}">
                                                {{ number_format($onHand, 0) }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-center font-medium">
                                            {{ number_format($item->qty_requested, 0) }}
                                        </td>
                                        <td class="py-3 text-center">
                                            @if ($item->qty_approved !== null)
                                                <span class="text-amber-600 dark:text-amber-400">
                                                    {{ number_format($item->qty_approved, 0) }}
                                                </span>
                                            @else
                                                <span class="text-zinc-400">â€”</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-center">
                                            @if ($item->qty_issued > 0)
                                                <span class="text-green-600 dark:text-green-400">
                                                    {{ number_format($item->qty_issued, 0) }}
                                                </span>
                                            @else
                                                <span class="text-zinc-400">â€”</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-center">
                                            @if ($item->qty_approved !== null && $item->remaining_to_issue > 0)
                                                <span class="text-blue-600 dark:text-blue-400">
                                                    {{ number_format($item->remaining_to_issue, 0) }}
                                                </span>
                                            @elseif ($item->qty_approved !== null)
                                                <flux:badge color="green" size="sm">Complete</flux:badge>
                                            @else
                                                <span class="text-zinc-400">â€”</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>

            {{-- Request Info Sidebar --}}
            <div class="space-y-6">
                {{-- Request Details --}}
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Request Details</flux:heading>

                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">Status</dt>
                            <dd>
                                <flux:badge color="{{ $stockRequest->status->color() }}" size="sm">
                                    {{ $stockRequest->status->label() }}
                                </flux:badge>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">Requested By</dt>
                            <dd class="text-zinc-900 dark:text-white">{{ $stockRequest->requester?->name ?? 'Unknown' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">Requested At</dt>
                            <dd class="text-zinc-900 dark:text-white">{{ $stockRequest->created_at->format('M d, Y H:i') }}</dd>
                        </div>
                        @if ($stockRequest->handler)
                            <div class="flex justify-between">
                                <dt class="text-zinc-500 dark:text-zinc-400">Handled By</dt>
                                <dd class="text-zinc-900 dark:text-white">{{ $stockRequest->handler->name }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">Total Items</dt>
                            <dd class="text-zinc-900 dark:text-white">{{ $stockRequest->items->count() }}</dd>
                        </div>
                    </dl>

                    @if ($stockRequest->note)
                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Request Note</span>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $stockRequest->note }}</p>
                        </div>
                    @endif

                    @if ($stockRequest->handler_note)
                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Handler Note</span>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $stockRequest->handler_note }}</p>
                        </div>
                    @endif
                </flux:card>

                {{-- Order Info --}}
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Order Info</flux:heading>

                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">Order No</dt>
                            <dd>
                                <a href="{{ route('orders.show', $stockRequest->order) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                                    {{ $stockRequest->order?->order_no }}
                                </a>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">Customer</dt>
                            <dd class="text-zinc-900 dark:text-white">{{ $stockRequest->order?->customer?->name ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">Order Status</dt>
                            <dd>
                                @php
                                    $orderStatusColor = $stockRequest->order?->status->color() ?? 'zinc';
                                @endphp
                                <flux:badge color="{{ $orderStatusColor }}" size="sm">
                                    {{ $stockRequest->order?->status->label() ?? 'N/A' }}
                                </flux:badge>
                            </dd>
                        </div>
                    </dl>
                </flux:card>
            </div>
        </div>

        {{-- Back Button --}}
        <div class="mt-6">
            <flux:button variant="ghost" :href="route('store.stock-requests.index')" wire:navigate>
                <x-icon name="arrow_back" class="mr-1 size-4" />
                Back to Inbox
            </flux:button>
        </div>

        {{-- Review Modal --}}
        <flux:modal wire:model="showReviewModal" class="max-w-2xl">
            <div class="space-y-4">
                <flux:heading size="lg">Review Stock Request</flux:heading>

                {{-- Decision Toggle --}}
                <div class="flex gap-4">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" wire:model="reviewDecision" value="approve" class="text-green-600 focus:ring-green-500">
                        <span class="text-green-600 dark:text-green-400">Approve</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" wire:model="reviewDecision" value="decline" class="text-red-600 focus:ring-red-500">
                        <span class="text-red-600 dark:text-red-400">Decline</span>
                    </label>
                </div>

                {{-- Approved Quantities (only for approve) --}}
                @if ($reviewDecision === 'approve')
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <span class="mb-3 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Set Approved Quantities</span>
                        <div class="space-y-3">
                            @foreach ($stockRequest->items as $index => $item)
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" wire:key="approve-item-{{ $item->id }}">
                                    <div class="flex-1">
                                        <span class="font-medium">{{ $item->inventoryItem?->name }}</span>
                                        <span class="ml-2 text-sm text-zinc-500">
                                            (Requested: {{ number_format($item->qty_requested, 0) }}, On Hand: {{ number_format($item->inventoryItem?->stock?->qty_on_hand ?? 0, 0) }})
                                        </span>
                                    </div>
                                    <flux:input
                                        wire:model="approvedItems.{{ $index }}.qty_approved"
                                        type="number"
                                        min="0"
                                        max="{{ $item->qty_requested }}"
                                        step="1"
                                        class="w-24"
                                    />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Note --}}
                <flux:textarea
                    wire:model="reviewNote"
                    label="Note (optional)"
                    placeholder="Add a note for the requester..."
                    rows="2"
                />

                {{-- Actions --}}
                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showReviewModal', false)">Cancel</flux:button>
                    <flux:button
                        :variant="$reviewDecision === 'approve' ? 'primary' : 'danger'"
                        wire:click="submitReview"
                    >
                        {{ $reviewDecision === 'approve' ? 'Approve Request' : 'Decline Request' }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Fulfill Modal --}}
        <flux:modal wire:model="showFulfillModal" class="max-w-2xl">
            <div class="space-y-4">
                <flux:heading size="lg">Fulfill Stock Request</flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    Issue inventory items for this request. This will deduct from stock.
                </flux:text>

                {{-- Issue Quantities --}}
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <span class="mb-3 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Quantities to Issue</span>
                    <div class="space-y-3">
                        @foreach ($stockRequest->items as $index => $item)
                            @if ($item->remaining_to_issue > 0)
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" wire:key="issue-item-{{ $item->id }}">
                                    <div class="flex-1">
                                        <span class="font-medium">{{ $item->inventoryItem?->name }}</span>
                                        <div class="text-sm text-zinc-500">
                                            Approved: {{ number_format($item->qty_approved, 0) }} â€¢
                                            Issued: {{ number_format($item->qty_issued, 0) }} â€¢
                                            Remaining: {{ number_format($item->remaining_to_issue, 0) }} â€¢
                                            <span class="{{ ($item->inventoryItem?->stock?->qty_on_hand ?? 0) < $item->remaining_to_issue ? 'text-red-500' : '' }}">
                                                On Hand: {{ number_format($item->inventoryItem?->stock?->qty_on_hand ?? 0, 0) }}
                                            </span>
                                        </div>
                                    </div>
                                    <flux:input
                                        wire:model="issueItems.{{ $index }}.qty_to_issue"
                                        type="number"
                                        min="0"
                                        max="{{ min($item->remaining_to_issue, $item->inventoryItem?->stock?->qty_on_hand ?? 0) }}"
                                        step="1"
                                        class="w-24"
                                    />
                                </div>
                            @else
                                <div class="flex flex-col gap-3 opacity-50 sm:flex-row sm:items-center sm:justify-between" wire:key="issue-item-{{ $item->id }}">
                                    <div class="flex-1">
                                        <span class="font-medium">{{ $item->inventoryItem?->name }}</span>
                                        <flux:badge color="green" size="sm" class="ml-2">Fully Issued</flux:badge>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Note --}}
                <flux:textarea
                    wire:model="fulfillNote"
                    label="Note (optional)"
                    placeholder="Add a fulfillment note..."
                    rows="2"
                />

                {{-- Actions --}}
                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showFulfillModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="submitFulfill">
                        <x-icon name="download" class="mr-1 size-4" />
                        Issue Stock
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </flux:main>
</div>

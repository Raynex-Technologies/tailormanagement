<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('procurement.pos.index') }}" wire:navigate>{{ __('Purchase Orders') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $purchaseOrder->po_no }}</flux:breadcrumbs.item>
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
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ $purchaseOrder->po_no }}</flux:heading>
                    <flux:badge color="{{ $purchaseOrder->status->color() }}" size="lg">
                        {{ $purchaseOrder->status->label() }}
                    </flux:badge>
                </div>
                <flux:text class="mt-1">
                    {{ __('Supplier') }}: <span class="font-medium">{{ $purchaseOrder->supplier?->name ?? 'N/A' }}</span>
                    <span class="text-zinc-400">â€¢</span>
                    {{ $purchaseOrder->created_at->format('M d, Y H:i') }}
                </flux:text>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center gap-2">
                @can('markSent', $purchaseOrder)
                    <flux:button size="sm" variant="primary" wire:click="markAsSent" wire:confirm="Mark this PO as sent to supplier?">
                        <x-icon name="send" class="mr-1 size-4" />
                        {{ __('Mark as Sent') }}
                    </flux:button>
                @endcan

                @can('receive', $purchaseOrder)
                    <flux:button size="sm" variant="primary" :href="route('procurement.receiving.show', $purchaseOrder)" wire:navigate>
                        <x-icon name="local_shipping" class="mr-1 size-4" />
                        {{ __('Fulfill / Receive Stock') }}
                    </flux:button>
                @endcan

                @can('cancel', $purchaseOrder)
                    <flux:button size="sm" variant="ghost" class="text-red-600 hover:text-red-800" wire:click="cancel" wire:confirm="Are you sure you want to cancel this PO?">
                        <x-icon name="cancel" class="mr-1 size-4" />
                        {{ __('Cancel') }}
                    </flux:button>
                @endcan
            </div>
        </div>
    </flux:card>

    {{-- Status-based guidance for Storekeeper --}}
    @if ($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::Draft && auth()->user()->can('procurement.receive'))
        <flux:callout variant="warning" icon="exclamation-triangle">
            <strong>{{ __('Waiting for PO to be sent') }}</strong>
            <p class="mt-1 text-sm">{{ __('This Purchase Order must be marked as "Sent" before you can receive goods. Contact the accountant or manager to proceed.') }}</p>
        </flux:callout>
    @elseif (in_array($purchaseOrder->status, [\App\Enums\PurchaseOrderStatus::Sent, \App\Enums\PurchaseOrderStatus::PartiallyReceived]) && auth()->user()->can('procurement.receive'))
        <flux:callout variant="info" icon="truck">
            <div class="flex items-center justify-between">
                <div>
                    <strong>{{ __('Ready for Receiving') }}</strong>
                    <p class="mt-1 text-sm">{{ __('Click "Fulfill / Receive Stock" to record incoming goods and update inventory.') }}</p>
                </div>
                <flux:button size="sm" variant="primary" :href="route('procurement.receiving.show', $purchaseOrder)" wire:navigate>
                    <x-icon name="arrow_forward" class="mr-1 size-4" />
                    {{ __('Receive Now') }}
                </flux:button>
            </div>
        </flux:callout>
    @elseif ($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::Received)
        <flux:callout variant="success" icon="check-circle">
            <strong>{{ __('Fully Received') }}</strong>
            <p class="mt-1 text-sm">{{ __('All items have been received and stock has been updated.') }}</p>
        </flux:callout>
    @endif

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Total Amount') }}</flux:text>
            <flux:heading size="lg" class="mt-1 font-mono text-indigo-600 dark:text-indigo-400">
                {{ money_tzs($purchaseOrder->total) }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Items Ordered') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ number_format($totalOrdered, 2) }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Items Received') }}</flux:text>
            <flux:heading size="lg" class="mt-1 {{ $totalReceived >= $totalOrdered ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                {{ number_format($totalReceived, 2) }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Expected Date') }}</flux:text>
            <flux:heading size="md" class="mt-1">
                {{ $purchaseOrder->expected_date?->format('M d, Y') ?? '-' }}
            </flux:heading>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Items Table --}}
        <div class="lg:col-span-2 space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Order Items') }}</flux:heading>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Item') }}</flux:table.column>
                        <flux:table.column>{{ __('Qty Ordered') }}</flux:table.column>
                        <flux:table.column>{{ __('Qty Received') }}</flux:table.column>
                        <flux:table.column>{{ __('Pending') }}</flux:table.column>
                        <flux:table.column>{{ __('Unit Cost') }}</flux:table.column>
                        <flux:table.column>{{ __('Line Total') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($purchaseOrder->items as $item)
                            <flux:table.row>
                                <flux:table.cell>
                                    <span class="font-medium">{{ $item->item_name }}</span>
                                    @if ($item->inventoryItem)
                                        <span class="block text-xs text-zinc-500">SKU: {{ $item->inventoryItem->sku }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ number_format($item->qty_ordered, 2) }}</flux:table.cell>
                                <flux:table.cell class="{{ $item->qty_received >= $item->qty_ordered ? 'text-green-600 dark:text-green-400' : '' }}">
                                    {{ number_format($item->qty_received, 2) }}
                                </flux:table.cell>
                                <flux:table.cell class="{{ $item->pending_qty > 0 ? 'text-amber-600 dark:text-amber-400' : '' }}">
                                    {{ number_format($item->pending_qty, 2) }}
                                </flux:table.cell>
                                <flux:table.cell class="font-mono">{{ money_tzs($item->unit_cost) }}</flux:table.cell>
                                <flux:table.cell class="font-mono">{{ money_tzs($item->line_total) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                <div class="mt-4 flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <div class="text-right">
                        <flux:label>{{ __('Total') }}</flux:label>
                        <flux:heading size="lg" class="font-mono text-indigo-600 dark:text-indigo-400">
                            {{ money_tzs($purchaseOrder->total) }}
                        </flux:heading>
                    </div>
                </div>
            </flux:card>

            {{-- Goods Receipts --}}
            @if ($purchaseOrder->goodsReceipts->isNotEmpty())
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Goods Receipts') }}</flux:heading>

                    <div class="space-y-3">
                        @foreach ($purchaseOrder->goodsReceipts as $grn)
                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="font-medium">{{ $grn->grn_no }}</span>
                                        <span class="ml-2 text-sm text-zinc-500">
                                            {{ $grn->received_at->format('M d, Y H:i') }}
                                        </span>
                                    </div>
                                    <flux:badge color="green" size="sm">
                                        {{ $grn->items->count() }} {{ __('items') }}
                                    </flux:badge>
                                </div>
                                <div class="mt-2 text-sm text-zinc-500">
                                    {{ __('Received by') }}: {{ $grn->receiver?->name ?? 'N/A' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Details --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Details') }}</flux:heading>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Created') }}</dt>
                        <dd>{{ $purchaseOrder->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Created By') }}</dt>
                        <dd>{{ $purchaseOrder->creator?->name ?? 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Supplier') }}</dt>
                        <dd>{{ $purchaseOrder->supplier?->name ?? 'N/A' }}</dd>
                    </div>
                    @if ($purchaseOrder->purchaseRequest)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('From PR') }}</dt>
                            <dd>
                                <a href="{{ route('procurement.requests.show', $purchaseOrder->purchaseRequest) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                                    {{ $purchaseOrder->purchaseRequest->request_no }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($purchaseOrder->note)
                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:label>{{ __('Note') }}</flux:label>
                        <flux:text class="mt-1 whitespace-pre-wrap">{{ $purchaseOrder->note }}</flux:text>
                    </div>
                @endif
            </flux:card>

            {{-- Supplier Contact --}}
            @if ($purchaseOrder->supplier)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Supplier Contact') }}</flux:heading>

                    <dl class="space-y-2 text-sm">
                        @if ($purchaseOrder->supplier->contact_name)
                            <div>
                                <dt class="text-zinc-500">{{ __('Contact') }}</dt>
                                <dd>{{ $purchaseOrder->supplier->contact_name }}</dd>
                            </div>
                        @endif
                        @if ($purchaseOrder->supplier->phone)
                            <div>
                                <dt class="text-zinc-500">{{ __('Phone') }}</dt>
                                <dd>
                                    <a href="tel:{{ $purchaseOrder->supplier->phone }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                        {{ $purchaseOrder->supplier->phone }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                        @if ($purchaseOrder->supplier->email)
                            <div>
                                <dt class="text-zinc-500">{{ __('Email') }}</dt>
                                <dd>
                                    <a href="mailto:{{ $purchaseOrder->supplier->email }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                        {{ $purchaseOrder->supplier->email }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </flux:card>
            @endif
        </div>
    </div>
</flux:main>

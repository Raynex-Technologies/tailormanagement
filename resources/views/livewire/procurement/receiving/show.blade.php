<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('procurement.receiving.index') }}" wire:navigate>{{ __('Receiving') }}</flux:breadcrumbs.item>
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
                    <flux:heading size="xl">{{ __('Receive Goods') }}: {{ $purchaseOrder->po_no }}</flux:heading>
                    <flux:badge color="{{ $purchaseOrder->status->color() }}" size="lg">
                        {{ $purchaseOrder->status->label() }}
                    </flux:badge>
                </div>
                <flux:text class="mt-1">
                    {{ __('Supplier') }}: <span class="font-medium">{{ $purchaseOrder->supplier?->name ?? 'N/A' }}</span>
                </flux:text>
            </div>
        </div>
    </flux:card>

    {{-- Receiving Form --}}
    <form wire:submit="receive">
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Items to Receive') }}</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Item') }}</flux:table.column>
                    <flux:table.column>{{ __('Ordered') }}</flux:table.column>
                    <flux:table.column>{{ __('Already Received') }}</flux:table.column>
                    <flux:table.column>{{ __('Pending') }}</flux:table.column>
                    <flux:table.column>{{ __('Qty to Receive') }}</flux:table.column>
                    <flux:table.column>{{ __('Unit Cost') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($purchaseOrder->items as $item)
                        @php
                            $pendingQty = $item->qty_ordered - $item->qty_received;
                        @endphp
                        <flux:table.row wire:key="item-{{ $item->id }}">
                            <flux:table.cell>
                                <span class="font-medium">{{ $item->item_name }}</span>
                                @if ($item->inventoryItem)
                                    <span class="block text-xs text-zinc-500">SKU: {{ $item->inventoryItem->sku }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ number_format($item->qty_ordered, 2) }}</flux:table.cell>
                            <flux:table.cell class="text-green-600 dark:text-green-400">
                                {{ number_format($item->qty_received, 2) }}
                            </flux:table.cell>
                            <flux:table.cell class="{{ $pendingQty > 0 ? 'text-amber-600 dark:text-amber-400 font-medium' : '' }}">
                                {{ number_format($pendingQty, 2) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($pendingQty > 0)
                                    <flux:input
                                        type="number"
                                        wire:model.live="receivingItems.{{ $item->id }}.qty_received"
                                        step="0.01"
                                        min="0"
                                        max="{{ $pendingQty }}"
                                        class="w-24"
                                    />
                                @else
                                    <span class="text-green-600 dark:text-green-400">{{ __('Fully Received') }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($pendingQty > 0)
                                    <flux:input
                                        type="number"
                                        wire:model="receivingItems.{{ $item->id }}.unit_cost"
                                        step="1"
                                        min="0"
                                        class="w-28"
                                    />
                                @else
                                    <span class="font-mono text-zinc-500">{{ money_tzs($item->unit_cost) }}</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            {{-- Total & Note --}}
            <div class="mt-6 flex flex-col gap-4 border-t border-zinc-200 pt-4 dark:border-zinc-700 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex-1">
                    <flux:label for="note">{{ __('Receipt Note (optional)') }}</flux:label>
                    <flux:textarea id="note" wire:model="note" rows="2" class="max-w-md" placeholder="Notes about this delivery..." />
                </div>
                <div class="text-right">
                    <flux:label>{{ __('Total Qty to Receive') }}</flux:label>
                    <flux:heading size="lg" class="font-mono text-indigo-600 dark:text-indigo-400">
                        {{ number_format($totalToReceive, 2) }}
                    </flux:heading>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button type="button" variant="ghost" :href="route('procurement.pos.show', $purchaseOrder)" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" :disabled="$totalToReceive <= 0">
                    <flux:icon name="truck" class="mr-1 size-4" />
                    {{ __('Receive Goods') }}
                </flux:button>
            </div>
        </flux:card>
    </form>

    {{-- Previous Receipts --}}
    @if ($purchaseOrder->goodsReceipts->isNotEmpty())
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Previous Receipts') }}</flux:heading>

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
                                {{ $grn->items->sum('qty_received') }} {{ __('units') }}
                            </flux:badge>
                        </div>
                        <div class="mt-2 text-sm text-zinc-500">
                            {{ __('Received by') }}: {{ $grn->receiver?->name ?? 'N/A' }}
                        </div>
                        @if ($grn->note)
                            <div class="mt-1 text-sm text-zinc-500">
                                {{ $grn->note }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</flux:main>

<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Goods Receiving') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Header --}}
    <flux:card>
        <div>
            <flux:heading size="xl">{{ __('Goods Receiving') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Receive goods from purchase orders and update inventory.') }}</flux:text>
        </div>
    </flux:card>

    {{-- Search --}}
    <flux:card>
        <div class="w-full md:w-1/3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by PO no or supplier..." icon="magnifying-glass" />
        </div>
    </flux:card>

    {{-- PO Table --}}
    <flux:card>
        @if ($purchaseOrders->isEmpty())
            <div class="py-12 text-center">
                <flux:icon name="truck" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-4">{{ __('No orders pending receiving') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('All sent purchase orders have been fully received.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('PO No') }}</flux:table.column>
                    <flux:table.column>{{ __('Supplier') }}</flux:table.column>
                    <flux:table.column>{{ __('Total Items') }}</flux:table.column>
                    <flux:table.column>{{ __('Received') }}</flux:table.column>
                    <flux:table.column>{{ __('Pending') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($purchaseOrders as $po)
                        @php
                            $totalOrdered = $po->items->sum('qty_ordered');
                            $totalReceived = $po->items->sum('qty_received');
                            $pendingQty = $totalOrdered - $totalReceived;
                        @endphp
                        <flux:table.row wire:key="po-{{ $po->id }}">
                            <flux:table.cell class="font-medium">
                                {{ $po->po_no }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $po->supplier?->name ?? 'N/A' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ number_format($totalOrdered, 2) }}
                            </flux:table.cell>
                            <flux:table.cell class="text-green-600 dark:text-green-400">
                                {{ number_format($totalReceived, 2) }}
                            </flux:table.cell>
                            <flux:table.cell class="font-medium text-amber-600 dark:text-amber-400">
                                {{ number_format($pendingQty, 2) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="{{ $po->status->color() }}" size="sm">
                                    {{ $po->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="xs" variant="primary" :href="route('procurement.receiving.show', $po)" wire:navigate>
                                    <flux:icon name="truck" class="mr-1 size-4" />
                                    {{ __('Receive') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $purchaseOrders->links() }}
            </div>
        @endif
    </flux:card>
</flux:main>

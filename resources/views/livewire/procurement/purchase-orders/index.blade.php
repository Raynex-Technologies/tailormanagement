<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Purchase Orders') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Header --}}
    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Purchase Orders') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Manage purchase orders to suppliers.') }}</flux:text>
            </div>
        </div>
    </flux:card>

    {{-- Filters --}}
    <flux:card>
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="w-full md:w-1/3">
                <flux:input wire:model.blur="search" placeholder="Search orders..." icon="magnifying-glass" />
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" :variant="$statusFilter === null ? 'primary' : 'ghost'" wire:click="setStatusFilter(null)">
                    {{ __('All') }}
                </flux:button>
                @foreach ($statuses as $status)
                    <flux:button size="sm" :variant="$statusFilter === $status->value ? 'primary' : 'ghost'" wire:click="setStatusFilter('{{ $status->value }}')">
                        {{ $status->label() }}
                        @if (isset($statusCounts[$status->value]))
                            <span class="ml-1 text-xs opacity-75">({{ $statusCounts[$status->value] }})</span>
                        @endif
                    </flux:button>
                @endforeach
            </div>
        </div>
    </flux:card>

    {{-- PO Table --}}
    <flux:card>
        @if ($purchaseOrders->isEmpty())
            <div class="py-12 text-center">
                <x-icon name="shopping_cart" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-4">{{ __('No purchase orders found') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Purchase orders are created from approved purchase requests.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('PO No') }}</flux:table.column>
                    <flux:table.column>{{ __('Supplier') }}</flux:table.column>
                    <flux:table.column>{{ __('From PR') }}</flux:table.column>
                    <flux:table.column>{{ __('Total') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Expected') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($purchaseOrders as $po)
                        <flux:table.row wire:key="po-{{ $po->id }}">
                            <flux:table.cell class="font-medium">
                                {{ $po->po_no }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $po->supplier?->name ?? 'N/A' }}
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">
                                @if ($po->purchaseRequest)
                                    <a href="{{ route('procurement.requests.show', $po->purchaseRequest) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                                        {{ $po->purchaseRequest->request_no }}
                                    </a>
                                @else
                                    -
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="font-mono">
                                {{ money_tzs($po->total) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="{{ $po->status->color() }}" size="sm">
                                    {{ $po->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">
                                {{ $po->expected_date?->format('M d, Y') ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="xs" variant="ghost" :href="route('procurement.pos.show', $po)" wire:navigate>
                                    <x-icon name="visibility" class="size-4" />
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

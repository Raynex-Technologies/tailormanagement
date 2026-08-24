<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('procurement.requests.index') }}" wire:navigate>{{ __('Purchase Requests') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $purchaseRequest->request_no }}</flux:breadcrumbs.item>
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
                    <flux:heading size="xl">{{ $purchaseRequest->request_no }}</flux:heading>
                    <flux:badge color="{{ $purchaseRequest->status->color() }}" size="lg">
                        {{ $purchaseRequest->status->label() }}
                    </flux:badge>
                </div>
                <flux:text class="mt-1">
                    {{ __('Requested by') }}: <span class="font-medium">{{ $purchaseRequest->requester?->name ?? 'N/A' }}</span>
                    <span class="text-zinc-400">â€¢</span>
                    {{ $purchaseRequest->created_at->format('M d, Y H:i') }}
                </flux:text>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Storekeeper: Edit (if draft) --}}
                @can('update', $purchaseRequest)
                    <flux:button size="sm" variant="subtle" :href="route('procurement.requests.edit', $purchaseRequest)" wire:navigate>
                        <x-icon name="edit" class="mr-1 size-4" />
                        {{ __('Edit') }}
                    </flux:button>
                @endcan

                {{-- Storekeeper: Submit (if draft) --}}
                @can('submit', $purchaseRequest)
                    <flux:button size="sm" variant="primary" wire:click="submit" wire:confirm="Are you sure you want to submit this request for review?">
                        <x-icon name="send" class="mr-1 size-4" />
                        {{ __('Submit for Review') }}
                    </flux:button>
                @endcan

                {{-- Accountant: Convert to PO (if approved) --}}
                @can('convertToPo', $purchaseRequest)
                    <flux:button size="sm" variant="primary" wire:click="openConvertModal">
                        <x-icon name="shopping_cart" class="mr-1 size-4" />
                        {{ __('Convert to PO') }}
                    </flux:button>
                @endcan
            </div>
        </div>
    </flux:card>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Items') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ $purchaseRequest->items->count() }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Estimated Total') }}</flux:text>
            <flux:heading size="lg" class="mt-1 font-mono text-indigo-600 dark:text-indigo-400">
                {{ money_tzs($purchaseRequest->estimated_total) }}
            </flux:heading>
        </flux:card>
        @if ($purchaseRequest->reviewer)
            <flux:card class="text-center">
                <flux:text class="text-sm text-zinc-500">{{ __('Reviewed By') }}</flux:text>
                <flux:heading size="md" class="mt-1">
                    {{ $purchaseRequest->reviewer->name }}
                </flux:heading>
            </flux:card>
        @endif
        @if ($purchaseRequest->capitalAllocation)
            <flux:card class="text-center">
                <flux:text class="text-sm text-zinc-500">{{ __('Capital Allocation') }}</flux:text>
                <flux:heading size="md" class="mt-1">
                    <a href="{{ route('capital.show', $purchaseRequest->capitalAllocation) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                        {{ $purchaseRequest->capitalAllocation->allocation_no }}
                    </a>
                </flux:heading>
            </flux:card>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Items Table --}}
        <div class="lg:col-span-2">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Items') }}</flux:heading>

                @if ($purchaseRequest->status === \App\Enums\PurchaseRequestStatus::Submitted && auth()->user()->can('approve', $purchaseRequest))
                    {{-- Accountant Review Mode --}}
                    <div class="mb-4 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/30">
                        <flux:text class="text-sm text-blue-700 dark:text-blue-300">
                            <x-icon name="info" class="mr-1 inline size-4" />
                            {{ __('Review mode: You can adjust quantities and prices before approving.') }}
                        </flux:text>
                        @if ($availableBalance !== null)
                            <div class="mt-2">
                                <flux:text class="text-sm text-blue-700 dark:text-blue-300">
                                    {{ __('Available Capital Balance') }}: <strong class="font-mono">{{ money_tzs($availableBalance) }}</strong>
                                </flux:text>
                            </div>
                        @endif
                    </div>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Item') }}</flux:table.column>
                            <flux:table.column>{{ __('Qty') }}</flux:table.column>
                            <flux:table.column>{{ __('Unit Price') }}</flux:table.column>
                            <flux:table.column>{{ __('Line Total') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($purchaseRequest->items as $item)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <span class="font-medium">{{ $item->item_name }}</span>
                                        @if ($item->inventoryItem)
                                            <span class="block text-xs text-zinc-500">SKU: {{ $item->inventoryItem->sku }}</span>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:input
                                            wire:model.blur="reviewedItems.{{ $item->id }}.qty"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            class="w-24"
                                        />
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <x-money-input
                                            wire:model.blur="reviewedItems.{{ $item->id }}.unit_price_est"
                                            step="0.01"
                                            min="0"
                                            class="w-28"
                                        />
                                    </flux:table.cell>
                                    <flux:table.cell class="font-mono">
                                        @php
                                            $qty = $reviewedItems[$item->id]['qty'] ?? $item->qty;
                                            $price = $reviewedItems[$item->id]['unit_price_est'] ?? $item->unit_price_est;
                                        @endphp
                                        {{ money_tzs($qty * $price) }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>

                    <div class="mt-4 flex justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <div>
                            <flux:label for="reviewNote">{{ __('Review Note (optional)') }}</flux:label>
                            <flux:textarea id="reviewNote" wire:model="reviewNote" rows="2" class="w-full max-w-md" placeholder="Add a note..." />
                        </div>
                        <div class="text-right">
                            <flux:label>{{ __('Reviewed Total') }}</flux:label>
                            <flux:heading size="lg" class="font-mono text-indigo-600 dark:text-indigo-400">
                                {{ money_tzs($reviewedTotal) }}
                            </flux:heading>
                            @if ($availableBalance !== null && $reviewedTotal > $availableBalance)
                                <flux:text class="text-sm text-amber-600 dark:text-amber-400">
                                    {{ __('Approved total exceeds available capital; this approval will proceed without a capital deduction.') }}
                                </flux:text>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-3">
                        <flux:button variant="danger" wire:click="decline" wire:confirm="Are you sure you want to decline this request?">
                            <x-icon name="cancel" class="mr-1 size-4" />
                            {{ __('Decline') }}
                        </flux:button>
                        <flux:button variant="primary" wire:click="approve" wire:confirm="Are you sure you want to approve this request?">
                            <x-icon name="check_circle" class="mr-1 size-4" />
                            {{ __('Approve') }}
                        </flux:button>
                    </div>
                @else
                    {{-- Read-only View --}}
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Item') }}</flux:table.column>
                            <flux:table.column>{{ __('Qty') }}</flux:table.column>
                            <flux:table.column>{{ __('Unit Price Est.') }}</flux:table.column>
                            <flux:table.column>{{ __('Line Total') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($purchaseRequest->items as $item)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <span class="font-medium">{{ $item->item_name }}</span>
                                        @if ($item->inventoryItem)
                                            <span class="block text-xs text-zinc-500">SKU: {{ $item->inventoryItem->sku }}</span>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ number_format($item->qty, 2) }}</flux:table.cell>
                                    <flux:table.cell class="font-mono">{{ money_tzs($item->unit_price_est) }}</flux:table.cell>
                                    <flux:table.cell class="font-mono">{{ money_tzs($item->line_total_est) }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>

                    <div class="mt-4 flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <div class="text-right">
                            <flux:label>{{ __('Total') }}</flux:label>
                            <flux:heading size="lg" class="font-mono text-indigo-600 dark:text-indigo-400">
                                {{ money_tzs($purchaseRequest->estimated_total) }}
                            </flux:heading>
                        </div>
                    </div>
                @endif
            </flux:card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Details --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Details') }}</flux:heading>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Created') }}</dt>
                        <dd>{{ $purchaseRequest->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Requested By') }}</dt>
                        <dd>{{ $purchaseRequest->requester?->name ?? 'N/A' }}</dd>
                    </div>
                    @if ($purchaseRequest->reviewer)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Reviewed By') }}</dt>
                            <dd>{{ $purchaseRequest->reviewer->name }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($purchaseRequest->note)
                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:label>{{ __('Note') }}</flux:label>
                        <flux:text class="mt-1 whitespace-pre-wrap">{{ $purchaseRequest->note }}</flux:text>
                    </div>
                @endif
            </flux:card>

            {{-- Related PO --}}
            @if ($purchaseRequest->purchaseOrder)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Purchase Order') }}</flux:heading>

                    <a href="{{ route('procurement.pos.show', $purchaseRequest->purchaseOrder) }}" class="block rounded-lg border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" wire:navigate>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium">{{ $purchaseRequest->purchaseOrder->po_no }}</span>
                                <span class="block text-sm text-zinc-500">{{ $purchaseRequest->purchaseOrder->supplier?->name }}</span>
                            </div>
                            <flux:badge color="{{ $purchaseRequest->purchaseOrder->status->color() }}" size="sm">
                                {{ $purchaseRequest->purchaseOrder->status->label() }}
                            </flux:badge>
                        </div>
                    </a>
                </flux:card>
            @endif
        </div>
    </div>

    {{-- Convert to PO Modal --}}
    <flux:modal wire:model="showConvertModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Convert to Purchase Order') }}</flux:heading>

            <div>
                <flux:label for="supplierId">{{ __('Supplier') }} *</flux:label>
                <flux:select id="supplierId" wire:model="supplierId">
                    <flux:select.option value="">{{ __('-- Select Supplier --') }}</flux:select.option>
                    @foreach ($suppliers as $supplier)
                        <flux:select.option value="{{ $supplier->id }}">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @error('supplierId')
                    <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                @enderror
            </div>

            <div>
                <flux:label for="expectedDate">{{ __('Expected Delivery Date') }}</flux:label>
                <flux:input type="date" id="expectedDate" wire:model="expectedDate" />
            </div>

            <div>
                <flux:label for="poNote">{{ __('Note') }}</flux:label>
                <flux:textarea id="poNote" wire:model="poNote" rows="2" placeholder="Optional notes..." />
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showConvertModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="button" variant="primary" wire:click="convertToPo">
                    <x-icon name="shopping_cart" class="mr-1 size-4" />
                    {{ __('Create PO') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</flux:main>

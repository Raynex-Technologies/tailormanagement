<div
    class="flex min-h-screen flex-col bg-zinc-100 p-3 dark:bg-zinc-950 lg:h-screen lg:overflow-hidden"
    x-on:pos-scan-ready.window="$nextTick(() => document.getElementById('itemSearch')?.focus())"
    x-on:open-pos-receipt.window="window.open($event.detail.url, '_blank', 'noopener')"
>
    @if (session('success'))
        <div class="mb-3 shrink-0">
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        </div>
    @endif

    @error('cart')
        <div class="mb-3 shrink-0">
            <flux:callout variant="danger" icon="exclamation-circle">{{ $message }}</flux:callout>
        </div>
    @enderror

    <div class="mb-3 flex shrink-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-zinc-950 dark:text-white sm:text-2xl">{{ __('Point of Sale') }}</h1>
            <p class="text-sm text-zinc-500">{{ __('Search items, build the cart, and complete payment.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-white px-3 py-1.5 text-sm font-medium text-zinc-600 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800">
                {{ now()->format('M d, Y H:i') }}
            </span>
        </div>
    </div>

    <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-12">
        <section class="flex min-h-0 flex-col rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-8">
            <div class="shrink-0 border-b border-zinc-200 p-3 dark:border-zinc-800">
                <flux:label for="itemSearch">{{ __('Item Search') }}</flux:label>
                <flux:input
                    id="itemSearch"
                    wire:model.live.debounce.200ms="itemSearch"
                    wire:keydown.enter.prevent="addFirstSearchMatch($event.target.value)"
                    icon="magnifying-glass"
                    placeholder="Scan barcode or search item name, SKU, or category..."
                    autofocus
                />
                @error('itemSearch') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-3">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @forelse ($items as $item)
                        @php
                            $available = max(0, (float)(($item->pos_on_hand??0)-($item->pos_reserved??0)));
                            $imageUrl = $item->featured_image_url;
                        @endphp
                        <button
                            type="button"
                            wire:key="pos-item-{{ $item->id }}"
                            wire:click="addItem({{ $item->id }})"
                            wire:loading.attr="disabled"
                            @class([
                                'group overflow-hidden rounded-lg border p-0 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--tm-accent)]',
                                'border-zinc-200 hover:-translate-y-0.5 hover:border-[var(--tm-accent)] hover:shadow-md dark:border-zinc-700 dark:hover:border-[var(--tm-accent)]' => $available > 0,
                                'cursor-not-allowed border-zinc-200 opacity-55 dark:border-zinc-800' => $available <= 0,
                            ])
                            @disabled($available <= 0 && $item->variant_mode === 'simple')
                        >
                            <div class="h-20 bg-zinc-100 dark:bg-zinc-800 2xl:h-24">
                                @if ($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-zinc-100 to-zinc-200 text-zinc-400 dark:from-zinc-800 dark:to-zinc-900 dark:text-zinc-600">
                                        <i class="fa-duotone fa-box-open-full text-3xl"></i>
                                    </div>
                                @endif
                            </div>

                            <div class="space-y-2 p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-[13px] font-semibold text-zinc-950 dark:text-white 2xl:text-sm">{{ $item->name }}</p>
                                        <p class="mt-0.5 truncate text-[10px] text-zinc-500 2xl:text-[11px]">
                                            {{ $item->sku }}{{ $item->category ? ' / '.$item->category->name : '' }}
                                        </p>
                                    </div>
                                    <span @class([
                                        'shrink-0 rounded-full px-1.5 py-0.5 text-[11px] font-semibold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $available > 0,
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $available <= 0,
                                    ])>
                                        {{ number_format($available, 2) }}
                                    </span>
                                </div>

                                @if($item->variant_mode === 'variants')<p class="text-xs text-zinc-500">{{ $item->pos_variation_count }} {{ __('variations') }} / {{ number_format($available,2) }} {{ __('available') }}</p>@endif
                                <div class="flex flex-wrap gap-2 items-center justify-between">
                                    <span class="font-mono text-[13px] font-bold text-zinc-950 dark:text-white 2xl:text-sm">{{ $item->pos_min_price != $item->pos_max_price ? __('From').' ' : '' }}{{ $item->pos_min_price === null ? __('Price not set') : money_tzs($item->pos_min_price) }}</span>
                                    <span class="text-[10px] font-medium text-zinc-500 group-hover:text-[var(--tm-accent)] dark:group-hover:text-[var(--tm-accent)] 2xl:text-[11px]">
                                        {{ $item->variant_mode === 'variants' ? __('Select Variation') : ($available > 0 ? __('Add') : __('Out of stock')) }}
                                    </span>
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full rounded-lg border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-700">
                            <flux:text class="text-zinc-500">{{ __('No matching inventory items found.') }}</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="min-h-0 overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-4">
            <div class="border-b border-zinc-200 p-3 dark:border-zinc-800">
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] lg:grid-cols-1 xl:grid-cols-[minmax(0,1fr)_auto]">
                    <div>
                        <flux:label for="customerSearch">{{ __('Customer') }}</flux:label>
                        @if ($customerId)
                            <div class="mt-2 flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm dark:border-emerald-800 dark:bg-emerald-900/20">
                                <span class="font-medium text-emerald-800 dark:text-emerald-200">{{ $selectedCustomerName }}</span>
                                <button type="button" wire:click="clearCustomer" class="text-emerald-700 hover:text-emerald-900 dark:text-emerald-300">
                                    {{ __('Remove') }}
                                </button>
                            </div>
                        @else
                            <flux:input
                                id="customerSearch"
                                wire:model.live.debounce.300ms="customerSearch"
                                icon="user"
                                placeholder="Walk-in or search customer..."
                            />

                            @if ($customerSearch !== '' && $customers->isNotEmpty())
                                <div class="mt-2 max-h-44 overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-950">
                                    @foreach ($customers as $customer)
                                        <button type="button" wire:click="selectCustomer({{ $customer->id }})" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                            <span class="font-medium text-zinc-900 dark:text-white">{{ $customer->name }}</span>
                                            <span class="text-xs text-zinc-500">{{ $customer->phone ?? $customer->code }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>

                    @can('customers.create')
                        <div class="flex items-end">
                            <flux:button type="button" icon="user-plus" wire:click="openCustomerModal">
                                {{ __('New') }}
                            </flux:button>
                        </div>
                    @endcan
                </div>
            </div>

            <div class="p-3">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('Cart') }}</h2>
                    @if ($cart)
                        <button
                            type="button"
                            wire:click="clearCart"
                            wire:confirm="{{ __('Clear the current cart?') }}"
                            class="text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400"
                        >
                            {{ __('Empty') }}
                        </button>
                    @endif
                </div>

                <div class="space-y-3">
                    @forelse ($cart as $line)
                        @php
                            $lineTotal = max(0, ($line['price'] * $line['quantity']) - ($line['discount_amount'] ?? 0));
                        @endphp
                        <div wire:key="cart-line-{{ $line['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $line['name'] }}</p>
                                    @if($line['variation'] ?? null)<p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $line['variation'] }}</p>@endif
                                    <p class="text-xs text-zinc-500">{{ $line['sku'] }} &middot; {{ money_tzs($line['price']) }}</p>
                                </div>
                                <button type="button" wire:click="removeItem({{ $line['id'] }})" class="text-zinc-400 hover:text-red-600">
                                    <i class="fa-duotone fa-trash"></i>
                                </button>
                            </div>

                            <div class="mt-3 grid grid-cols-[auto_4.5rem_auto] items-center gap-2">
                                <flux:button size="sm" variant="ghost" icon="minus" wire:click="decreaseQty({{ $line['id'] }})" />
                                <input
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value="{{ $line['quantity'] }}"
                                    wire:change="updateQty({{ $line['id'] }}, $event.target.value)"
                                    class="h-9 rounded-lg border border-zinc-300 bg-white px-2 text-center text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
                                >
                                <flux:button size="sm" variant="ghost" icon="plus" wire:click="increaseQty({{ $line['id'] }})" />
                            </div>
                            @error('cart.unit_'.$line['id'].'.quantity') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                            <div class="mt-3 flex items-center justify-between text-sm">
                                <span class="text-zinc-500">{{ __('Line total') }}</span>
                                <span class="font-mono font-semibold text-zinc-900 dark:text-white">{{ money_tzs($lineTotal) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-zinc-300 py-8 text-center dark:border-zinc-700">
                            <i class="fa-duotone fa-cart-shopping mb-2 text-2xl text-zinc-300 dark:text-zinc-600"></i>
                            <flux:text class="text-zinc-500">{{ __('Cart is empty.') }}</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>

            <form wire:submit="completeSale" class="space-y-3 border-t border-zinc-200 p-3 dark:border-zinc-800">
                <div class="space-y-2 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950">
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">{{ __('Subtotal') }}</span>
                        <span class="font-mono">{{ money_tzs($this->subtotal) }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-money-input wire:model.blur="discountAmount" min="0" step="0.01" label="{{ __('Discount') }}" />
                        <x-money-input wire:model.blur="taxAmount" min="0" step="0.01" label="{{ __('Tax') }}" />
                    </div>
                    <div class="flex justify-between border-t border-zinc-200 pt-3 text-lg font-bold dark:border-zinc-700">
                        <span>{{ __('Total') }}</span>
                        <span class="font-mono">{{ money_tzs($this->total) }}</span>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    <flux:select wire:model.live="paymentMethod" label="{{ __('Payment Method') }}" required>
                        <flux:select.option value="cash">{{ __('Cash') }}</flux:select.option>
                        <flux:select.option value="mobile_money">{{ __('Mobile Money') }}</flux:select.option>
                        <flux:select.option value="card">{{ __('Card') }}</flux:select.option>
                        <flux:select.option value="bank_transfer">{{ __('Bank Transfer') }}</flux:select.option>
                        <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
                    </flux:select>

                    <x-money-input wire:model.blur="amountPaid" min="0" step="0.01" label="{{ __('Amount Paid') }}" required />
                </div>
                @error('amountPaid') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                @if ($paymentMethod !== 'cash')
                    <flux:input wire:model="paymentReference" label="{{ __('Payment Reference (optional)') }}" />
                    @error('paymentReference') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @endif

                <div class="flex justify-between rounded-lg bg-emerald-50 p-3 text-emerald-900 dark:bg-emerald-900/20 dark:text-emerald-200">
                    <span class="font-medium">{{ __('Change Due') }}</span>
                    <span class="font-mono text-xl font-bold">{{ money_tzs($this->changeDue) }}</span>
                </div>

                <flux:button
                    type="submit"
                    variant="primary"
                    icon="check"
                    class="w-full justify-center"
                    wire:loading.attr="disabled"
                    :disabled="empty($cart)"
                >
                    <span wire:loading.remove wire:target="completeSale">{{ __('Complete & Print') }}</span>
                    <span wire:loading wire:target="completeSale">{{ __('Processing...') }}</span>
                </flux:button>
            </form>
        </aside>
    </div>

    <flux:modal wire:model="showCustomerModal" class="max-w-lg">
        <form wire:submit="createCustomer" class="space-y-4">
            <flux:heading size="lg">{{ __('New Customer') }}</flux:heading>

            <flux:input wire:model="newCustomerName" label="{{ __('Name') }}" required />
            @error('newCustomerName') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:input wire:model="newCustomerPhone" label="{{ __('Phone') }}" />
                    @error('newCustomerPhone') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <flux:input wire:model="newCustomerEmail" type="email" label="{{ __('Email') }}" />
                    @error('newCustomerEmail') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <flux:textarea wire:model="newCustomerAddress" label="{{ __('Address') }}" rows="2" />

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCustomerModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Create Customer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showReceiptModal" class="w-full max-w-xl">
        @if ($completedSale)
            <div class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Receipt') }} {{ $completedSale->sale_number }}</flux:heading>
                        <flux:text class="text-zinc-500">{{ __('Sale completed. Print the receipt or continue selling from this screen.') }}</flux:text>
                    </div>
                    <div class="flex gap-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showReceiptModal', false)">
                            {{ __('Close') }}
                        </flux:button>
                        <flux:button type="button" variant="primary" icon="printer" onclick="window.print()">
                            {{ __('Print') }}
                        </flux:button>
                    </div>
                </div>

                <div class="pos-receipt-print-area pos-receipt-modal-shell">
                    @include('pos.partials.receipt-slip', [
                        'sale' => $completedSale,
                        'settings' => $businessSettings,
                        'receiptUrl' => $completedSale->public_receipt_url,
                        'receiptQrCodeSvg' => $receiptQrCodeSvg,
                    ])
                </div>
            </div>
        @endif
    </flux:modal>

    <style>
        .pos-receipt-modal-shell {
            display: flex;
            justify-content: center;
            max-height: min(72vh, 48rem);
            overflow-y: auto;
            border-radius: 0.75rem;
            background: #ece7df;
            padding: 1.5rem 1rem;
        }

        .pos-receipt-slip {
            width: min(100%, 23rem);
            color: #18181b;
            filter: drop-shadow(0 12px 18px rgb(0 0 0 / 0.18));
        }

        .pos-receipt-edge {
            height: 14px;
            background:
                linear-gradient(135deg, transparent 8px, #fff 0) top left,
                linear-gradient(225deg, transparent 8px, #fff 0) top right;
            background-size: 16px 14px;
            background-repeat: repeat-x;
        }

        .pos-receipt-edge-bottom {
            transform: rotate(180deg);
        }

        .pos-receipt-body {
            background: #fff;
            padding: 2rem 2.25rem;
        }

        .pos-receipt-slip svg {
            width: 100%;
            height: 100%;
        }

        @media print {
            body * {
                visibility: hidden !important;
            }

            .pos-receipt-print-area,
            .pos-receipt-print-area * {
                visibility: visible !important;
            }

            .pos-receipt-print-area {
                position: fixed !important;
                inset: 0 !important;
                max-height: none !important;
                overflow: visible !important;
                align-items: flex-start;
                padding: 0 !important;
                background: #fff !important;
            }

            .pos-receipt-slip {
                width: 80mm;
                filter: none;
            }
        }
    </style>

    <flux:modal wire:model="showVariationModal" class="w-full max-w-lg">
        @if($variationProduct)
            <div class="space-y-5">
                <div><flux:heading size="lg">{{ $variationProduct->name }}</flux:heading><flux:text class="mt-1">{{ __('Select Variation') }}</flux:text></div>
                @php $chosenIds=array_map('intval',array_values($variationChoices)); $prior=[]; @endphp
                @foreach($variationProduct->options as $option)
                    <fieldset wire:key="pos-option-{{ $option->id }}"><legend class="mb-2 font-medium">{{ $option->name }}</legend><div class="flex flex-wrap gap-2">
                    @foreach($option->values as $value)
                        @php
                            $candidateIds=[...$prior,$value->id];
                            $offered=$variationProduct->variants->filter(fn($v)=>count(array_intersect($candidateIds,$v->selectedValues->pluck('id')->all()))===count($candidateIds));
                            $ready=$offered->filter(fn($v)=>$v->is_active && $v->stockUnit?->is_active && $v->stockUnit?->allocation_status==='ready' && $v->stockUnit?->selling_price !== null);
                            $hasStock=$ready->contains(fn($v)=>(float)($v->stockUnit?->stock?->qty_on_hand??0)-(float)($v->stockUnit?->stock?->qty_reserved??0)>=1);
                            $selected=($variationChoices['option_'.$option->id]??null)==$value->id;
                            $state=$offered->isEmpty()?__('Not offered'):($ready->isEmpty()?__('Unavailable'):(! $hasStock?__('Out of stock'):''));
                        @endphp
                        <button type="button" wire:click="chooseVariationValue({{ $option->id }},{{ $value->id }})" @disabled(! $hasStock) aria-pressed="{{ $selected ? 'true' : 'false' }}" class="min-h-11 rounded-lg border border-zinc-300 px-4 py-2 text-sm disabled:opacity-50 dark:border-zinc-600" @if($selected) style="background:var(--tm-accent);color:var(--tm-accent-foreground);border-color:var(--tm-accent)" @endif>
                            <span class="block">{{ $value->name }}</span>@if($state)<span class="block text-xs mt-1">{{ $state }}</span>@endif
                        </button>
                    @endforeach
                    </div></fieldset>
                    @php if(isset($variationChoices['option_'.$option->id])){$prior[]=(int)$variationChoices['option_'.$option->id];} @endphp
                @endforeach
                @php
                    sort($chosenIds);
                    $resolved=$variationProduct->variants->filter(fn($v)=>$v->selectedValues->pluck('id')->sort()->values()->all()===$chosenIds);
                    $selectedVariant=$resolved->count()===1 && count($chosenIds)===$variationProduct->options->count()?$resolved->first():null;
                @endphp
                @if($selectedVariant)
                    <div class="rounded-xl bg-zinc-100 p-4 dark:bg-zinc-800"><flux:heading>{{ $selectedVariant->display_name }}</flux:heading><flux:text>{{ __('SKU') }}: {{ $selectedVariant->stockUnit->sku }}</flux:text><p class="mt-2 text-lg font-semibold">{{ money_tzs($selectedVariant->stockUnit->selling_price) }}</p><flux:text>{{ number_format((float)($selectedVariant->stockUnit->stock?->qty_on_hand??0)-(float)($selectedVariant->stockUnit->stock?->qty_reserved??0),2) }} {{ __('available') }}</flux:text></div>
                @else<flux:text>{{ __('Choose one value for each option to see its exact price and availability.') }}</flux:text>@endif
                @error('cart')<p role="alert" class="text-red-600 dark:text-red-300">{{ $message }}</p>@enderror
                <div class="flex gap-3"><flux:button class="flex-1" wire:click="$set('showVariationModal',false)">{{ __('Cancel') }}</flux:button><flux:button class="flex-1" variant="primary" wire:click="addSelectedVariation" :disabled="! $selectedVariant" wire:loading.attr="disabled">{{ __('Add to Cart') }}</flux:button></div>
            </div>
        @endif
    </flux:modal>
</div>

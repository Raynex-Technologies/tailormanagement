<div>
    <flux:main class="p-0">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mb-4 rounded-2xl p-4 border border-green-200 dark:border-green-800/50 bg-green-50 dark:bg-green-900/20">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-green-100 dark:bg-green-900/50 shrink-0">
                        <i class="fa-duotone fa-circle-check size-5 text-green-500"></i>
                    </div>
                    <p class="font-medium text-green-700 dark:text-green-300">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-2xl p-4 border border-red-200 dark:border-red-800/50 bg-red-50 dark:bg-red-900/20">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-red-100 dark:bg-red-900/50 shrink-0">
                        <i class="fa-duotone fa-circle-exclamation size-5 text-red-500"></i>
                    </div>
                    <p class="font-medium text-red-700 dark:text-red-300">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        {{-- Order Header Card --}}
        <div class="relative mb-6 overflow-hidden rounded-2xl border border-zinc-700/30 p-5 text-white shadow-lg sm:p-6" style="background: linear-gradient(135deg, var(--tm-hero) 0%, color-mix(in srgb, var(--tm-hero) 88%, #ffffff 12%) 100%);" data-theme-hero data-orders-workspace-header>
            <flux:breadcrumbs class="relative z-10 mb-5 text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item :href="route('orders.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Orders') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item class="!text-white">{{ $order->order_no }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            {{-- Subtle sewing pattern overlay --}}
            <svg class="absolute inset-0 w-full h-full pointer-events-none opacity-[0.03]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="sewing-pattern" x="0" y="0" width="60" height="60" patternUnits="userSpaceOnUse">
                        {{-- Scissors --}}
                        <path d="M15 10 C12 8, 8 8, 8 12 C8 16, 12 16, 15 14 L20 18 L15 22 C12 20, 8 20, 8 24 C8 28, 12 28, 15 26 L22 20 L28 20" stroke="white" fill="none" stroke-width="0.8"/>
                        {{-- Thread spool --}}
                        <rect x="42" y="8" width="10" height="14" rx="2" stroke="white" fill="none" stroke-width="0.6"/>
                        <line x1="44" y1="11" x2="50" y2="11" stroke="white" stroke-width="0.4"/>
                        <line x1="44" y1="14" x2="50" y2="14" stroke="white" stroke-width="0.4"/>
                        <line x1="44" y1="17" x2="50" y2="17" stroke="white" stroke-width="0.4"/>
                        {{-- Needle with thread --}}
                        <line x1="10" y1="42" x2="30" y2="52" stroke="white" stroke-width="0.6" stroke-dasharray="2,3"/>
                        <ellipse cx="8" cy="41" rx="2" ry="3" stroke="white" fill="none" stroke-width="0.6"/>
                        {{-- Button --}}
                        <circle cx="48" cy="44" r="6" stroke="white" fill="none" stroke-width="0.6"/>
                        <circle cx="46" cy="42" r="0.8" fill="white"/>
                        <circle cx="50" cy="42" r="0.8" fill="white"/>
                        <circle cx="46" cy="46" r="0.8" fill="white"/>
                        <circle cx="50" cy="46" r="0.8" fill="white"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#sewing-pattern)" />
            </svg>

            <div class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-semibold tracking-tight text-white">{{ __('Order :order', ['order' => $order->order_no]) }}</h1>
                        <flux:badge color="{{ $order->status->color() }}" size="lg">
                            {{ $order->status->label() }}
                        </flux:badge>
                        @if ($order->isOverdue())
                            <flux:badge color="red" size="lg">Overdue</flux:badge>
                        @endif
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-white/75">
                        <span><i class="fa-duotone fa-user mr-1.5 text-white/50" aria-hidden="true"></i>{{ $order->customer?->name ?? __('Unknown customer') }}</span>
                        <span><i class="fa-duotone fa-calendar mr-1.5 text-white/50" aria-hidden="true"></i>{{ __('Due :date', ['date' => $order->due_date?->format('M d, Y') ?? __('N/A')]) }}</span>
                        @if ($canViewFinancials)
                            <span><i class="fa-duotone fa-wallet mr-1.5 text-white/50" aria-hidden="true"></i>{{ __('Payment: :status', ['status' => $order->computed_payment_status->label()]) }}</span>
                        @endif
                    </div>
                    @if ($order->assignedTailor || $tailorDisplay !== 'Unassigned')
                        <p class="mt-1.5 text-sm text-white/50">
                            <i class="fa-duotone fa-user-tag size-3.5 text-white/40 mr-1"></i>
                            Tailor: <span class="text-white/70">{{ $tailorDisplay }}</span>
                        </p>
                    @endif
                </div>

                {{-- Primary actions + 3-dot menu --}}
                <div class="grid w-full gap-2 sm:w-auto sm:grid-flow-col sm:auto-cols-max sm:items-center">
                    {{-- Change Status --}}
                    @if ($canChangeStatus && $nextStatuses->isNotEmpty())
                        <flux:button size="sm" class="w-full sm:w-auto" wire:click="openStatusModal">
                            <i class="fa-duotone fa-arrow-rotate-right size-3.5 mr-1"></i>
                            Change Status
                        </flux:button>
                    @endif

                    {{-- Assign Tailor --}}
                    @if ($showAssignTailorButton)
                        <flux:button size="sm" variant="subtle" class="w-full sm:w-auto" wire:click="openAssignTailorModal">
                            <x-icon name="person" class="mr-1 size-4" />
                            Assign Tailor
                        </flux:button>
                    @endif

                    {{-- Create Delivery Note --}}
                    @if ($canCreateDeliveryNote)
                        <flux:button size="sm" variant="subtle" class="w-full sm:w-auto" wire:click="openDeliveryNoteModal">
                            <i class="fa-duotone fa-truck size-3.5 mr-1"></i>
                            Create Delivery Note
                        </flux:button>
                    @endif

                    {{-- Mark Completed --}}
                    @if ($canMarkCompleted && $order->status === \App\Enums\OrderStatus::Delivered)
                        <flux:button size="sm" variant="primary" class="w-full sm:w-auto" wire:click="markCompleted" wire:confirm="Are you sure you want to mark this order as completed?">
                            <x-icon name="check" class="mr-1 size-4" />
                            Mark Completed
                        </flux:button>
                    @endif

                    {{-- 3-dot menu for Edit / Cancel / Delete --}}
                    @if ($canEdit || $canDelete || ($canChangeStatus && !in_array($order->status, [\App\Enums\OrderStatus::Completed, \App\Enums\OrderStatus::Cancelled])))
                        <flux:dropdown position="bottom" align="end">
                            <button type="button" class="flex h-9 w-full items-center justify-center rounded-lg bg-white/10 text-white/70 transition-colors hover:bg-white/20 hover:text-white sm:size-8" aria-label="{{ __('More order actions') }}">
                                <i class="fa-duotone fa-ellipsis-vertical size-4"></i>
                            </button>

                            <flux:menu>
                                @if ($canEdit)
                                    <flux:menu.item :href="route('orders.edit', $order)" wire:navigate>
                                        <i class="fa-duotone fa-pen-to-square text-sm text-zinc-400 mr-2"></i>
                                        Edit Order
                                    </flux:menu.item>
                                @endif

                                @if ($canChangeStatus && !in_array($order->status, [\App\Enums\OrderStatus::Completed, \App\Enums\OrderStatus::Cancelled]))
                                    <flux:menu.item wire:click="cancelOrder" wire:confirm="Are you sure you want to cancel this order?">
                                        <i class="fa-duotone fa-ban text-sm text-amber-500 mr-2"></i>
                                        Cancel Order
                                    </flux:menu.item>
                                @endif

                                @if ($canDelete)
                                    <flux:menu.separator />
                                    <flux:menu.item
                                        wire:click="deleteOrder"
                                        wire:confirm="Delete this order? This will soft-delete the order and related records, and return issued inventory."
                                        class="text-red-600 dark:text-red-400"
                                    >
                                        <i class="fa-duotone fa-trash text-sm mr-2"></i>
                                        Delete Order
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>
            </div>
        </div>

        {{-- Financial Summary Cards --}}
        @if ($canViewFinancials)
            <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Total Amount --}}
                <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                    <div class="flex items-center justify-center size-11 rounded-xl bg-indigo-50 dark:bg-indigo-900/30">
                        <i class="fa-duotone fa-coins size-5 text-indigo-500"></i>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl font-bold tracking-tight font-mono text-indigo-600 dark:text-indigo-400">{{ number_format($order->total, 0) }}</p>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Total Amount</p>
                    </div>
                </div>

                {{-- Paid Amount --}}
                <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                    <div class="flex items-center justify-center size-11 rounded-xl bg-emerald-50 dark:bg-emerald-900/30">
                        <i class="fa-duotone fa-circle-check size-5 text-emerald-500"></i>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl font-bold tracking-tight font-mono text-green-600 dark:text-green-400">{{ number_format($order->paid_amount, 0) }}</p>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Paid Amount</p>
                    </div>
                </div>

                {{-- Balance Due --}}
                @php $balanceColor = $order->balance_due > 0 ? 'red' : 'emerald'; @endphp
                <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                    <div class="flex items-center justify-center size-11 rounded-xl bg-{{ $balanceColor }}-50 dark:bg-{{ $balanceColor }}-900/30">
                        <i class="fa-duotone fa-scale-balanced size-5 text-{{ $balanceColor }}-500"></i>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl font-bold tracking-tight font-mono text-{{ $balanceColor }}-600 dark:text-{{ $balanceColor }}-400">{{ number_format($order->balance_due, 0) }}</p>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Balance Due</p>
                    </div>
                </div>

                {{-- Due Date --}}
                <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                    <div class="flex items-center justify-center size-11 rounded-xl bg-amber-50 dark:bg-amber-900/30">
                        <i class="fa-duotone fa-calendar size-5 text-amber-500"></i>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $order->due_date?->format('M d, Y') ?? 'N/A' }}</p>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Due Date</p>
                    </div>
                    @if ($canEdit)
                        <flux:button size="sm" variant="ghost" class="mt-3" wire:click="openDueDateModal">
                            <x-icon name="edit_calendar" class="mr-1 size-4" />
                            Change Due Date
                        </flux:button>
                    @endif
                </div>
            </div>
        @else
            {{-- Non-financial summary --}}
            <div class="mb-6 grid gap-4 sm:grid-cols-2">
                {{-- Due Date --}}
                <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                    <div class="flex items-center justify-center size-11 rounded-xl bg-amber-50 dark:bg-amber-900/30">
                        <i class="fa-duotone fa-calendar size-5 text-amber-500"></i>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $order->due_date?->format('M d, Y') ?? 'N/A' }}</p>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Due Date</p>
                    </div>
                </div>

                {{-- Priority --}}
                <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                    <div class="flex items-center justify-center size-11 rounded-xl bg-blue-50 dark:bg-blue-900/30">
                        <i class="fa-duotone fa-flag size-5 text-blue-500"></i>
                    </div>
                    <div class="mt-3">
                        <flux:badge color="{{ $order->priority?->color() ?? 'zinc' }}" size="lg">
                            {{ $order->priority?->label() ?? 'N/A' }}
                        </flux:badge>
                        <p class="mt-1.5 text-sm text-zinc-500 dark:text-zinc-400">Priority</p>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Main Content Area --}}
            <div class="xl:col-span-2 min-w-0 space-y-6">
                {{-- Order Items --}}
                <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-lime-100 dark:bg-lime-900/30">
                            <i class="fa-duotone fa-shirt text-lime-600 dark:text-lime-400" aria-hidden="true"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Order Items</h3>
                    </div>

                    <div class="space-y-4">
                        @forelse ($orderPresentation['groups'] as $group)
                            @if ($group['type'] === 'package')
                                @php($package = $group['package'])
                                <article class="overflow-hidden rounded-2xl border border-violet-200 bg-violet-50/40 dark:border-violet-800/60 dark:bg-violet-950/20" data-order-package-group="{{ $package['id'] }}">
                                    <div class="flex flex-col gap-4 border-b border-violet-200/80 p-4 dark:border-violet-800/60 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="flex min-w-0 gap-3">
                                            @if ($package['image_url'])
                                                <img src="{{ $package['image_url'] }}" alt="" class="size-14 shrink-0 rounded-xl object-cover">
                                            @else
                                                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-300">
                                                    <i class="fa-duotone fa-box-open-full" aria-hidden="true"></i>
                                                </span>
                                            @endif
                                            <div class="min-w-0">
                                                <h4 class="font-semibold text-zinc-950 dark:text-white">{{ $package['name'] }}</h4>
                                                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Package revision :revision', ['revision' => $package['revision']]) }}</p>
                                                @if ($package['description'])
                                                    <p class="mt-2 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $package['description'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        @if ($canViewFinancials)
                                            <div class="shrink-0 sm:text-right">
                                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Configured package value') }}</p>
                                                <p class="mt-1 font-mono text-lg font-semibold text-violet-700 dark:text-violet-300">{{ money_currency($package['configured_total'], config('app.currency', 'TZS')) }}</p>
                                                <p class="mt-1 text-xs text-zinc-500">{{ __('Informational grouping total') }}</p>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="space-y-3 p-4">
                                        @foreach ($group['lines'] as $displayLine)
                                            @include('livewire.orders.partials.display-line', ['displayLine' => $displayLine])
                                        @endforeach
                                    </div>

                                    @if ($package['is_customized'])
                                        @php($adjustment = (float) $package['adjustment'])
                                        <div class="border-t border-violet-200/80 bg-white/60 p-4 dark:border-violet-800/60 dark:bg-zinc-900/30" data-package-customization>
                                            @if ($canViewFinancials)
                                                <div class="grid gap-2 text-sm sm:grid-cols-2">
                                                    <div>
                                                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Original package value') }}</span>
                                                        <strong class="ml-1 font-mono text-zinc-800 dark:text-zinc-100">{{ money_currency($package['original_total'], config('app.currency', 'TZS')) }}</strong>
                                                    </div>
                                                    <div class="sm:text-right">
                                                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Adjustment') }}</span>
                                                        <strong class="ml-1 font-mono {{ $adjustment > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $adjustment > 0 ? '+' : ($adjustment < 0 ? '−' : '') }}{{ money_currency(abs($adjustment), config('app.currency', 'TZS')) }}</strong>
                                                    </div>
                                                </div>
                                            @endif
                                            @if ($package['customizations'])
                                                <div class="mt-3 space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                    @foreach ($package['customizations'] as $customization)
                                                        <p><span class="font-medium text-violet-700 dark:text-violet-300">{{ __('Customized:') }}</span> {{ $customization }}</p>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </article>
                            @else
                                @if ($orderPresentation['has_packages'])
                                    <div class="flex items-center gap-3 pt-2">
                                        <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
                                        <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $group['label'] }}</h4>
                                        <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
                                    </div>
                                @endif
                                @foreach ($group['lines'] as $displayLine)
                                    @include('livewire.orders.partials.display-line', ['displayLine' => $displayLine])
                                @endforeach
                            @endif
                        @empty
                            <div class="py-10 text-center">
                                <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                                    <i class="fa-duotone fa-shirt size-7 text-zinc-400 dark:text-zinc-500" aria-hidden="true"></i>
                                </div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No order items') }}</p>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No order lines have been added yet.') }}</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Totals Summary --}}
                    @if ($canViewFinancials)
                        <div class="mt-4 flex justify-end">
                            <div class="w-full max-w-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-4 space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-zinc-600 dark:text-zinc-400">Subtotal</span>
                                    <span class="font-mono text-zinc-900 dark:text-white">{{ number_format($order->subtotal, 0) }}</span>
                                </div>
                                @if ($order->discount > 0)
                                    <div class="flex justify-between text-sm">
                                        <span class="text-zinc-600 dark:text-zinc-400">Discount</span>
                                        <span class="font-mono text-red-600 dark:text-red-400">-{{ number_format($order->discount, 0) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between border-t border-zinc-200 dark:border-zinc-700 pt-2 text-lg font-semibold">
                                    <span class="text-zinc-900 dark:text-white">Total</span>
                                    <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ number_format($order->total, 0) }}</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Storefront Fulfillment / Shipment Manager --}}
                @if ($canManageStorefrontOperations && $isStorefrontOrder)
                    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 space-y-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-900/30">
                                    <i class="fa-duotone fa-truck-fast text-sky-600 dark:text-sky-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Storefront Fulfillment</h3>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Manage customer-visible order and shipment progress.</p>
                                </div>
                            </div>
                            <flux:badge color="{{ $order->fulfillment_status?->color() ?: 'zinc' }}">
                                {{ $order->fulfillment_status?->label() ?: 'Pending' }}
                            </flux:badge>
                        </div>

                        <form wire:submit="updateStorefrontFulfillmentStatus" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model="newFulfillmentStatus" label="Fulfillment Status">
                                    @foreach ($fulfillmentStatuses as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="fulfillmentNote" label="Customer Note (optional)" />
                            </div>
                            @error('newFulfillmentStatus')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('fulfillmentNote')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <div class="mt-4 flex justify-end">
                                <flux:button type="submit" variant="primary">Update Fulfillment</flux:button>
                            </div>
                        </form>

                        <form wire:submit="saveShipmentDetails" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                            <h4 class="font-semibold text-zinc-900 dark:text-white">Shipment Details</h4>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model="shipmentStatus" label="Shipment Status">
                                    <flux:select.option value="pending">Pending</flux:select.option>
                                    <flux:select.option value="packed">Packed</flux:select.option>
                                    <flux:select.option value="shipped">Shipped</flux:select.option>
                                    <flux:select.option value="delivered">Delivered</flux:select.option>
                                    <flux:select.option value="cancelled">Cancelled</flux:select.option>
                                </flux:select>
                                <flux:input wire:model="shipmentCarrierName" label="Carrier Name" />
                                <flux:input wire:model="shipmentTrackingNumber" label="Tracking Number" />
                                <flux:input wire:model="shipmentTrackingUrl" label="Tracking URL" />
                                <flux:input wire:model="shipmentShippedAt" type="datetime-local" label="Shipped At" />
                                <flux:input wire:model="shipmentDeliveredAt" type="datetime-local" label="Delivered At" />
                            </div>
                            <flux:textarea class="mt-4" wire:model="shipmentNotes" label="Shipment Notes" rows="2" />

                            @error('shipmentStatus')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('shipmentCarrierName')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('shipmentTrackingNumber')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('shipmentTrackingUrl')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('shipmentShippedAt')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('shipmentDeliveredAt')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            <div class="mt-4 flex justify-end">
                                <flux:button type="submit" variant="primary">Save Shipment</flux:button>
                            </div>
                        </form>

                        @if ($shipments->isNotEmpty())
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                                <h4 class="font-semibold text-zinc-900 dark:text-white">Shipment Timeline</h4>
                                <div class="mt-3 space-y-2">
                                    @foreach ($shipments as $shipment)
                                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/40">
                                            <div>
                                                <p class="font-medium text-zinc-900 dark:text-white">{{ str($shipment->status)->replace('_', ' ')->title() }}</p>
                                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ $shipment->carrier_name ?: 'Carrier pending' }}
                                                    @if ($shipment->tracking_number)
                                                        &bull; {{ $shipment->tracking_number }}
                                                    @endif
                                                </p>
                                            </div>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $shipment->updated_at?->format('M d, Y H:i') }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Custom Tailoring Progress Manager --}}
                @if ($canManageStorefrontOperations && $isTailoringOrder)
                    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 space-y-5">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/30">
                                <i class="fa-duotone fa-scissors text-violet-600 dark:text-violet-400"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Custom Progress Updates</h3>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">Publish tailoring milestones and optional payment requests to the customer portal.</p>
                            </div>
                        </div>

                        <form wire:submit="publishCustomProgressUpdate" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model="customStageKey" label="Stage">
                                    @foreach ($customStageOptions as $stageKey => $stageLabel)
                                        <flux:select.option value="{{ $stageKey }}">{{ $stageLabel }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="customStageLabel" label="Stage Label Override (optional)" />
                                <x-money-input wire:model.blur="customRequestedPaymentAmount" step="0.01" min="0" label="Requested Payment Amount (optional)" />
                                <flux:input wire:model="customRequestedPaymentNote" label="Requested Payment Note (optional)" />
                            </div>
                            <flux:textarea class="mt-4" wire:model="customProgressNote" label="Progress Note" rows="3" />
                            <label class="mt-3 flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                                <span>Visible to Customer</span>
                                <input type="checkbox" wire:model="customProgressVisible" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                            </label>

                            @error('customStageKey')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('customStageLabel')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('customProgressNote')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('customRequestedPaymentAmount')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('customRequestedPaymentNote')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            <div class="mt-4 flex justify-end">
                                <flux:button type="submit" variant="primary">Publish Progress Update</flux:button>
                            </div>
                        </form>

                        @if ($order->customProgressUpdates->isNotEmpty())
                            <div class="space-y-2">
                                @foreach ($order->customProgressUpdates->sortByDesc('id') as $progress)
                                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/40">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-medium text-zinc-900 dark:text-white">{{ $progress->stage_label }}</p>
                                                @if ($progress->note)
                                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $progress->note }}</p>
                                                @endif
                                                @if ($progress->requested_payment_amount)
                                                    <p class="mt-1 text-sm font-medium text-amber-600 dark:text-amber-400">
                                                        Payment Request: {{ number_format((float) $progress->requested_payment_amount, 2) }}
                                                        @if ($progress->requested_payment_note)
                                                            &bull; {{ $progress->requested_payment_note }}
                                                        @endif
                                                    </p>
                                                @endif
                                            </div>
                                            <div class="text-right text-xs text-zinc-500 dark:text-zinc-400">
                                                <p>{{ $progress->created_at?->format('M d, Y H:i') }}</p>
                                                <p>{{ $progress->is_customer_visible ? 'Customer Visible' : 'Internal' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Materials Panel --}}
                @if ($canViewMaterials)
                    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30">
                                    <i class="fa-duotone fa-swatchbook text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Materials</h3>
                            </div>
                            @if ($canManageMaterials && !$orderIsFinal)
                                <flux:button size="sm" variant="subtle" :href="route('orders.stock-requests', $order)" wire:navigate>
                                    <x-icon name="add" class="mr-1 size-4" />
                                    Request Materials
                                </flux:button>
                            @endif
                        </div>

                        @if ($materials->isNotEmpty())
                            <div class="space-y-3">
                                @foreach ($materials as $material)
                                    <div class="flex flex-col gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <span class="font-medium text-zinc-900 dark:text-white">
                                                {{ $material['inventory_item']?->name ?? 'Unknown Item' }}
                                            </span>
                                            <span class="ml-2 text-sm text-zinc-500 dark:text-zinc-400">
                                                ({{ $material['inventory_item']?->sku ?? '-' }})
                                            </span>
                                        </div>
                                        <div class="text-right text-sm">
                                            <div class="text-zinc-600 dark:text-zinc-400">
                                                Requested: <span class="font-medium">{{ number_format($material['qty_requested'], 0) }}</span>
                                            </div>
                                            <div class="text-green-600 dark:text-green-400">
                                                Issued: <span class="font-medium">{{ number_format($material['qty_issued'], 0) }}</span>
                                            </div>
                                            @if ($material['pending'] > 0)
                                                <div class="text-amber-600 dark:text-amber-400">
                                                    Pending: <span class="font-medium">{{ number_format($material['pending'], 0) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="py-10 text-center">
                                <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
                                    <i class="fa-duotone fa-swatchbook size-7 text-zinc-400 dark:text-zinc-500"></i>
                                </div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">No materials requested</p>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">No materials have been requested yet.</p>
                                @if ($canManageMaterials && !$orderIsFinal)
                                    <flux:button size="sm" class="mt-4" :href="route('orders.stock-requests', $order)" wire:navigate>
                                        Request Materials
                                    </flux:button>
                                @endif
                            </div>
                        @endif

                        {{-- Recent Stock Requests Summary --}}
                        @if ($stockRequests->isNotEmpty())
                            <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Recent Requests</p>
                                <div class="space-y-2">
                                    @foreach ($stockRequests->take(3) as $request)
                                        <div class="flex flex-col gap-1 text-sm sm:flex-row sm:items-center sm:justify-between">
                                            <span class="text-zinc-600 dark:text-zinc-400">
                                                {{ $request->request_no }} &bull; {{ $request->items->count() }} item(s)
                                            </span>
                                            <flux:badge size="sm" color="{{ $request->status->color() }}">
                                                {{ $request->status->label() }}
                                            </flux:badge>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Delivery Note --}}
                @if ($order->deliveryNote)
                    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                                <i class="fa-duotone fa-truck text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Delivery Note</h3>
                        </div>

                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="flex items-start justify-between">
                                <div class="space-y-1.5">
                                    <p class="font-medium text-zinc-900 dark:text-white">{{ $order->deliveryNote->delivery_note_no }}</p>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        <i class="fa-duotone fa-calendar size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                        Delivered: {{ $order->deliveryNote->delivered_at->format('M d, Y H:i') }}
                                    </p>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        <i class="fa-duotone fa-user size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                        By: {{ $order->deliveryNote->deliveredBy?->name ?? 'N/A' }}
                                    </p>
                                    @if ($order->deliveryNote->received_by_name)
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                            <i class="fa-duotone fa-handshake size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                            Received by: {{ $order->deliveryNote->received_by_name }}
                                            @if ($order->deliveryNote->received_by_phone)
                                                ({{ $order->deliveryNote->received_by_phone }})
                                            @endif
                                        </p>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="subtle" :href="route('delivery-notes.show', $order->deliveryNote)" wire:navigate>
                                        View
                                    </flux:button>
                                    <flux:button size="sm" variant="subtle" :href="route('delivery-notes.print', $order->deliveryNote)" target="_blank">
                                        <x-icon name="print" class="mr-1 size-4" />
                                        Print
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1 min-w-0 space-y-6">
                {{-- Order Info --}}
                <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                            <i class="fa-duotone fa-circle-info text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Order Info</h3>
                    </div>

                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">
                                <i class="fa-duotone fa-calendar size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                Order Date
                            </dt>
                            <dd class="text-zinc-900 dark:text-white">
                                {{ $order->order_date?->format('M d, Y') ?? $order->created_at->format('M d, Y') }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">
                                <i class="fa-duotone fa-user size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                Created By
                            </dt>
                            <dd class="text-zinc-900 dark:text-white">{{ $order->creator?->name ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">
                                <i class="fa-duotone fa-flag size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                Priority
                            </dt>
                            <dd>
                                <flux:badge color="{{ $order->priority?->color() ?? 'zinc' }}" size="sm">
                                    {{ $order->priority?->label() ?? 'N/A' }}
                                </flux:badge>
                            </dd>
                        </div>
                        @if ($canViewFinancials)
                            <div class="flex justify-between">
                                <dt class="text-zinc-500 dark:text-zinc-400">
                                    <i class="fa-duotone fa-wallet size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                    Payment Status
                                </dt>
                                <dd>
                                    <flux:badge color="{{ $order->payment_status->color() }}" size="sm">
                                        {{ $order->payment_status->label() }}
                                    </flux:badge>
                                </dd>
                            </div>
                        @endif
                        @if ($order->branch)
                            <div class="flex justify-between">
                                <dt class="text-zinc-500 dark:text-zinc-400">
                                    <i class="fa-duotone fa-building size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                    Branch
                                </dt>
                                <dd class="text-zinc-900 dark:text-white">{{ $order->branch->name }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Canonical Invoice / Financial Document --}}
                @if ($canViewFinancials)
                    <section class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50" aria-labelledby="order-invoice-heading" data-order-invoice-section>
                        <div class="flex items-start gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-lime-100 dark:bg-lime-900/30">
                                <i class="fa-duotone fa-file-invoice text-lime-700 dark:text-lime-400" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 id="order-invoice-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Invoice') }}</h3>

                                @if ($orderInvoice)
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <a
                                            href="{{ route('invoices.show', $orderInvoice) }}"
                                            class="break-all text-base font-semibold text-navy-800 underline decoration-lime-400 decoration-2 underline-offset-4 transition-colors hover:text-lime-700 focus-visible:rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-500 dark:text-lime-300 dark:hover:text-lime-200"
                                            wire:navigate
                                            data-order-invoice-number
                                        >
                                            {{ $orderInvoice->invoice_no }}
                                        </a>
                                        <flux:badge color="{{ $invoiceFinancialSummary['status']->color() }}" size="sm" data-order-invoice-status>
                                            {{ $invoiceFinancialSummary['status']->label() }}
                                        </flux:badge>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if ($orderInvoice)
                            <dl class="mt-4 divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                <div class="flex items-center justify-between gap-3 py-2 first:pt-0">
                                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</dt>
                                    <dd class="min-w-0 break-words text-right font-mono font-semibold text-zinc-900 dark:text-white" data-order-invoice-total>{{ money_tzs($invoiceFinancialSummary['total']) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Paid') }}</dt>
                                    <dd class="min-w-0 break-words text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400" data-order-invoice-paid>{{ money_tzs($invoiceFinancialSummary['paid']) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-2 last:pb-0">
                                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Balance') }}</dt>
                                    <dd class="min-w-0 break-words text-right font-mono font-semibold {{ $invoiceFinancialSummary['balance'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}" data-order-invoice-balance>{{ money_tzs($invoiceFinancialSummary['balance']) }}</dd>
                                </div>
                            </dl>

                            <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                <a
                                    href="{{ route('invoices.show', $orderInvoice) }}"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-lime-400 px-3 py-2 text-sm font-semibold text-navy-900 transition-colors hover:bg-lime-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-500"
                                    wire:navigate
                                    data-order-invoice-view-action
                                >
                                    <i class="fa-duotone fa-eye" aria-hidden="true"></i>
                                    {{ __('View Invoice') }}
                                </a>
                                @if ($canPrintInvoice)
                                    <a
                                        href="{{ route('invoices.print', $orderInvoice) }}"
                                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-navy-800 transition-colors hover:border-lime-400 hover:bg-lime-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-500 dark:border-zinc-600 dark:text-white dark:hover:border-lime-500 dark:hover:bg-lime-950/30"
                                        target="_blank"
                                        rel="noopener"
                                        data-order-invoice-print-action
                                    >
                                        <i class="fa-duotone fa-print" aria-hidden="true"></i>
                                        {{ __('Print Invoice') }}
                                    </a>
                                @endif
                            </div>
                        @else
                            <div class="mt-4 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900/30" data-order-invoice-unavailable>
                                <p class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Invoice unavailable') }}</p>
                                <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __('No accessible invoice is linked to this historical order. No new invoice was created.') }}</p>
                            </div>
                        @endif
                    </section>
                @endif

                {{-- Payment History --}}
                @if ($canViewPayments || $canRecordPayments)
                    <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50" data-payment-history-sidebar>
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                                    <i class="fa-duotone fa-receipt text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                                </div>
                                <h3 class="truncate text-lg font-semibold text-zinc-900 dark:text-white">
                                    {{ $canViewPayments ? __('Payment History') : __('Payments') }}
                                </h3>
                            </div>

                            @if ($canRecordPayments)
                                <livewire:orders.payments.panel :order="$order" wire:key="payments-panel-{{ $order->id }}" />
                            @endif
                        </div>

                        @if ($canViewPayments)
                            @if ($order->payments->isNotEmpty())
                                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach ($order->payments->sortByDesc('paid_at') as $payment)
                                        <div class="py-3 first:pt-0 last:pb-0" data-payment-history-entry>
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white" data-payment-history-title>
                                                        {{ __(':method Payment', ['method' => $payment->paymentMethod?->name ?? __('Default')]) }}
                                                    </p>
                                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400" data-payment-history-date>
                                                        {{ $payment->paid_at?->format('M d, Y · H:i') ?? __('Date unavailable') }}
                                                    </p>
                                                </div>
                                                <div class="shrink-0 text-right">
                                                    <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400" data-payment-history-amount>
                                                        {{ money_tzs($payment->amount) }}
                                                    </p>
                                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400" data-payment-history-method>
                                                        {{ $payment->paymentMethod?->name ?? __('Default') }}
                                                    </p>
                                                </div>
                                            </div>

                                            @if ($payment->reference || $payment->receiver || $payment->note)
                                                <div class="mt-2 space-y-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                                    @if ($payment->reference || $payment->receiver)
                                                        <p>
                                                            @if ($payment->reference)
                                                                {{ __('Ref: :reference', ['reference' => $payment->reference]) }}
                                                            @endif
                                                            @if ($payment->reference && $payment->receiver)
                                                                <span aria-hidden="true">·</span>
                                                            @endif
                                                            @if ($payment->receiver)
                                                                {{ __('Received by :name', ['name' => $payment->receiver->name]) }}
                                                            @endif
                                                        </p>
                                                    @endif
                                                    @if ($payment->note)
                                                        <p class="break-words">{{ $payment->note }}</p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="py-4 text-center">
                                    <x-icon name="payments" class="mx-auto size-9 text-zinc-300 dark:text-zinc-600" />
                                    <p class="mt-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('No payments recorded') }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Record the first payment when received.') }}</p>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                {{-- Customer Info --}}
                @if ($order->customer)
                    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/30">
                                <i class="fa-duotone fa-user text-violet-600 dark:text-violet-400" aria-hidden="true"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Customer</h3>
                        </div>

                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-zinc-500 dark:text-zinc-400">
                                    <i class="fa-duotone fa-user size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                    Name
                                </dt>
                                <dd class="text-zinc-900 dark:text-white">{{ $order->customer->name }}</dd>
                            </div>
                            @if ($order->customer->phone)
                                <div class="flex justify-between">
                                    <dt class="text-zinc-500 dark:text-zinc-400">
                                        <i class="fa-duotone fa-phone size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                        Phone
                                    </dt>
                                    <dd class="text-zinc-900 dark:text-white">{{ $order->customer->phone }}</dd>
                                </div>
                            @endif
                            @if ($order->customer->email)
                                <div class="flex justify-between">
                                    <dt class="text-zinc-500 dark:text-zinc-400">
                                        <i class="fa-duotone fa-envelope size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                        Email
                                    </dt>
                                    <dd class="text-zinc-900 dark:text-white truncate max-w-[150px]">{{ $order->customer->email }}</dd>
                                </div>
                            @endif
                            @if ($order->customer->address)
                                <div>
                                    <dt class="text-zinc-500 dark:text-zinc-400">
                                        <i class="fa-duotone fa-location-dot size-3.5 text-zinc-400 dark:text-zinc-500"></i>
                                        Address
                                    </dt>
                                    <dd class="mt-1 text-zinc-900 dark:text-white">{{ $order->customer->address }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif

                {{-- Notes --}}
                @if ($order->notes)
                    <div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-700/50">
                                <i class="fa-duotone fa-note-sticky text-zinc-600 dark:text-zinc-400" aria-hidden="true"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Notes</h3>
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 whitespace-pre-wrap">{{ $order->notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Status Change Modal --}}
        <flux:modal wire:model="showStatusModal" class="max-w-md">
            <div class="space-y-4">
                <flux:heading size="lg">Change Order Status</flux:heading>

                <flux:select wire:model="newStatus" label="New Status">
                    <flux:select.option value="">-- Select Status --</flux:select.option>
                    @foreach ($nextStatuses as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                @error('newStatus')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showStatusModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="changeStatus">Update Status</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Assign Tailor Modal --}}
        <flux:modal wire:model="showAssignTailorModal" class="max-w-md">
            <div class="space-y-4">
                <flux:heading size="lg">Assign Tailor</flux:heading>

                <flux:select wire:model="selectedTailorId" label="Tailor">
                    <flux:select.option value="">-- No Tailor --</flux:select.option>
                    @foreach ($tailors as $tailor)
                        <flux:select.option value="{{ $tailor->id }}">{{ $tailor->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showAssignTailorModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="assignTailor">Assign</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Delivery Note Modal --}}
        <flux:modal wire:model="showDeliveryNoteModal" class="max-w-md">
            <div class="space-y-4">
                <flux:heading size="lg">Create Delivery Note</flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    Create a delivery note for this order. This will also mark the order as delivered.
                </flux:text>

                <flux:input
                    wire:model="receivedByName"
                    label="Received By (Name)"
                    placeholder="Customer or receiver name"
                />

                <flux:input
                    wire:model="receivedByPhone"
                    label="Received By (Phone)"
                    placeholder="Phone number"
                />

                @error('deliveryNote')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showDeliveryNoteModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="createDeliveryNote">Create Delivery Note</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Due Date Modal --}}
        <flux:modal wire:model="showDueDateModal" class="max-w-md">
            <div class="space-y-4">
                <flux:heading size="lg">Update Due Date</flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ $allowOrderDatesFlexibility
                        ? __('Choose a new due date for this order. Past dates are allowed.')
                        : __('Choose a new due date for this order. The date must be today or later.') }}
                </flux:text>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="text-zinc-500 dark:text-zinc-400">Current Due Date</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-white">
                        {{ $order->due_date?->format('M d, Y') ?? 'N/A' }}
                    </div>
                </div>

                @if ($allowOrderDatesFlexibility)
                    <flux:input
                        wire:model="updatedDueDate"
                        type="date"
                        label="New Due Date"
                    />
                @else
                    <flux:input
                        wire:model="updatedDueDate"
                        type="date"
                        label="New Due Date"
                        min="{{ now()->toDateString() }}"
                    />
                @endif

                @error('updatedDueDate')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showDueDateModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="updateDueDate">Update Due Date</flux:button>
                </div>
            </div>
        </flux:modal>
    </flux:main>
</div>

<div>
    <flux:main class="p-6">
        {{-- Page Header --}}
        <div class="mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('orders.index')" wire:navigate>Orders</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $order->order_no }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                {{ session('error') }}
            </div>
        @endif

        {{-- Order Header (visible to all who can view order) --}}
        <flux:card class="mb-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <flux:heading size="xl">{{ $order->order_no }}</flux:heading>
                        @php
                            $statusColor = match($order->status) {
                                \App\Enums\OrderStatus::New => 'blue',
                                \App\Enums\OrderStatus::InProgress => 'amber',
                                \App\Enums\OrderStatus::Ready => 'purple',
                                \App\Enums\OrderStatus::Delivered => 'green',
                                \App\Enums\OrderStatus::Completed => 'green',
                                \App\Enums\OrderStatus::Cancelled => 'red',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge color="{{ $statusColor }}" size="lg">
                            {{ $order->status->label() }}
                        </flux:badge>
                        @if ($order->isOverdue())
                            <flux:badge color="red" size="lg">Overdue</flux:badge>
                        @endif
                    </div>
                    <flux:text class="mt-1">
                        Customer: <span class="font-medium text-zinc-900 dark:text-white">{{ $order->customer?->name ?? 'N/A' }}</span>
                        @if ($order->customer?->phone)
                            <span class="text-zinc-500"> • {{ $order->customer->phone }}</span>
                        @endif
                    </flux:text>
                </div>

                {{-- Actions --}}
                <div class="flex flex-wrap items-center gap-2">
                    {{-- Materials/Stock Requests - for storekeeper (hide when order is completed/delivered) --}}
                    @if ($canViewMaterials && !$orderIsFinal)
                        <flux:button size="sm" variant="subtle" :href="route('orders.stock-requests', $order)" wire:navigate>
                            <x-icon name="download" class="mr-1 size-4" />
                            Stock Requests
                        </flux:button>
                    @endif

                    @if ($canEdit)
                        <flux:button size="sm" variant="subtle" :href="route('orders.edit', $order)" wire:navigate>
                            <x-icon name="edit" class="mr-1 size-4" />
                            Edit
                        </flux:button>
                    @endif

                    @if ($order->invoice)
                        <flux:button size="sm" variant="subtle" :href="route('invoices.show', $order->invoice)" wire:navigate>
                            <x-icon name="description" class="mr-1 size-4" />
                            Invoice
                        </flux:button>
                    @endif

                    @if ($canChangeStatus && $nextStatuses->isNotEmpty())
                        <flux:button size="sm" variant="subtle" wire:click="openStatusModal">
                            <x-icon name="refresh" class="mr-1 size-4" />
                            Change Status
                        </flux:button>
                    @endif

                    @if ($showAssignTailorButton)
                        <flux:button size="sm" variant="subtle" wire:click="openAssignTailorModal">
                            <x-icon name="person" class="mr-1 size-4" />
                            Assign Tailor
                        </flux:button>
                    @endif

                    @if ($canCreateDeliveryNote)
                        <flux:button size="sm" wire:click="openDeliveryNoteModal">
                            <x-icon name="description" class="mr-1 size-4" />
                            Create Delivery Note
                        </flux:button>
                    @endif

                    @if ($canMarkCompleted && $order->status === \App\Enums\OrderStatus::Delivered)
                        <flux:button size="sm" variant="primary" wire:click="markCompleted" wire:confirm="Are you sure you want to mark this order as completed?">
                            <x-icon name="check" class="mr-1 size-4" />
                            Mark Completed
                        </flux:button>
                    @endif

                    @if ($canChangeStatus && !in_array($order->status, [\App\Enums\OrderStatus::Completed, \App\Enums\OrderStatus::Cancelled]))
                        <flux:button size="sm" variant="ghost" wire:click="cancelOrder" wire:confirm="Are you sure you want to cancel this order?">
                            <x-icon name="close" class="mr-1 size-4 text-red-500" />
                            Cancel
                        </flux:button>
                    @endif
                </div>
            </div>
        </flux:card>

        {{-- Financial Summary Cards - ONLY visible if canViewFinancials --}}
        @if ($canViewFinancials)
            <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <flux:card class="text-center">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Amount</flux:text>
                    <flux:heading size="lg" class="mt-1 font-mono text-indigo-600 dark:text-indigo-400">
                        {{ number_format($order->total, 0) }}
                    </flux:heading>
                </flux:card>

                <flux:card class="text-center">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Paid Amount</flux:text>
                    <flux:heading size="lg" class="mt-1 font-mono text-green-600 dark:text-green-400">
                        {{ number_format($order->paid_amount, 0) }}
                    </flux:heading>
                </flux:card>

                <flux:card class="text-center">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Balance Due</flux:text>
                    @php
                        $balanceColor = $order->balance_due > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400';
                    @endphp
                    <flux:heading size="lg" class="mt-1 font-mono {{ $balanceColor }}">
                        {{ number_format($order->balance_due, 0) }}
                    </flux:heading>
                </flux:card>

                <flux:card class="text-center">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Due Date</flux:text>
                    <flux:heading size="lg" class="mt-1">
                        {{ $order->due_date?->format('M d, Y') ?? '—' }}
                    </flux:heading>
                </flux:card>

                <flux:card class="text-center">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Assigned Tailor</flux:text>
                    <flux:heading size="lg" class="mt-1">
                        {{ $order->assignedTailor?->name ?? '—' }}
                    </flux:heading>
                </flux:card>
            </div>
        @else
            {{-- Non-financial summary for storekeeper/others --}}
            <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:card class="text-center">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Due Date</flux:text>
                    <flux:heading size="lg" class="mt-1">
                        {{ $order->due_date?->format('M d, Y') ?? '—' }}
                    </flux:heading>
                </flux:card>

                <flux:card class="text-center">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Assigned Tailor</flux:text>
                    <flux:heading size="lg" class="mt-1">
                        {{ $order->assignedTailor?->name ?? '—' }}
                    </flux:heading>
                </flux:card>

                <flux:card class="text-center">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Priority</flux:text>
                    @php
                        $priorityColor = match($order->priority) {
                            \App\Enums\Priority::Low => 'zinc',
                            \App\Enums\Priority::Normal => 'blue',
                            \App\Enums\Priority::High => 'amber',
                            \App\Enums\Priority::Urgent => 'red',
                            default => 'zinc',
                        };
                    @endphp
                    <flux:badge color="{{ $priorityColor }}" size="lg" class="mt-1">
                        {{ $order->priority?->label() ?? 'N/A' }}
                    </flux:badge>
                </flux:card>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Main Content Area --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Order Lines --}}
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Order Items</flux:heading>

                    <div class="space-y-4">
                        @forelse ($order->lines as $line)
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ $line->item_name }}</span>
                                        @if ($line->notes)
                                            <p class="mt-1 text-sm text-zinc-500">{{ $line->notes }}</p>
                                        @endif
                                    </div>
                                    {{-- Only show pricing if user can view financials --}}
                                    @if ($canViewFinancials)
                                        <div class="text-right">
                                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                {{ number_format($line->qty, 0) }} × {{ number_format($line->unit_price, 0) }}
                                            </div>
                                            <div class="font-mono font-medium text-zinc-900 dark:text-white">
                                                {{ number_format($line->line_total, 0) }}
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-right">
                                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                Qty: {{ number_format($line->qty, 0) }}
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Measurements --}}
                                @if ($line->measurement && !empty($line->measurement->measurements))
                                    <div class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                        <span class="text-xs font-medium uppercase text-zinc-500">Measurements</span>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($line->measurement->measurements as $key => $value)
                                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-sm dark:bg-zinc-700">
                                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $key }}:</span>
                                                    <span class="ml-1 text-zinc-600 dark:text-zinc-400">{{ $value }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-center text-zinc-500 py-4">No order lines found.</p>
                        @endforelse
                    </div>

                    {{-- Totals Summary - ONLY if can view financials --}}
                    @if ($canViewFinancials)
                        <div class="mt-4 flex justify-end">
                            <div class="w-full max-w-xs space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-zinc-600 dark:text-zinc-400">Subtotal</span>
                                    <span class="font-mono">{{ number_format($order->subtotal, 0) }}</span>
                                </div>
                                @if ($order->discount > 0)
                                    <div class="flex justify-between text-sm">
                                        <span class="text-zinc-600 dark:text-zinc-400">Discount</span>
                                        <span class="font-mono text-red-600">-{{ number_format($order->discount, 0) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between border-t border-zinc-200 pt-2 text-lg font-semibold dark:border-zinc-700">
                                    <span>Total</span>
                                    <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ number_format($order->total, 0) }}</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </flux:card>

                {{-- Materials Panel - ONLY for users with orders.materials.view permission --}}
                @if ($canViewMaterials)
                    <flux:card>
                        <div class="mb-4 flex items-center justify-between">
                            <flux:heading size="lg">Materials</flux:heading>
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
                                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                                        <div>
                                            <span class="font-medium text-zinc-900 dark:text-white">
                                                {{ $material['inventory_item']?->name ?? 'Unknown Item' }}
                                            </span>
                                            <span class="ml-2 text-sm text-zinc-500">
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
                            <div class="flex flex-col items-center justify-center py-8 text-center">
                                <x-icon name="archive" class="size-12 text-zinc-300 dark:text-zinc-600" />
                                <flux:text class="mt-2 text-zinc-500">No materials requested yet.</flux:text>
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
                                <flux:text class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Recent Requests</flux:text>
                                <div class="space-y-2">
                                    @foreach ($stockRequests->take(3) as $request)
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-zinc-600 dark:text-zinc-400">
                                                {{ $request->request_no }} • {{ $request->items->count() }} item(s)
                                            </span>
                                            @php
                                                $requestStatusColor = match($request->status->value) {
                                                    'requested' => 'blue',
                                                    'approved' => 'green',
                                                    'rejected' => 'red',
                                                    'fulfilled' => 'emerald',
                                                    'partially_fulfilled' => 'amber',
                                                    default => 'zinc',
                                                };
                                            @endphp
                                            <flux:badge size="sm" color="{{ $requestStatusColor }}">
                                                {{ $request->status->label() }}
                                            </flux:badge>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </flux:card>
                @endif

                {{-- Delivery Note --}}
                @if ($order->deliveryNote)
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Delivery Note</flux:heading>

                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $order->deliveryNote->delivery_note_no }}</span>
                                </p>
                                <p class="mt-1 text-sm text-zinc-500">
                                    Delivered: {{ $order->deliveryNote->delivered_at->format('M d, Y H:i') }}
                                </p>
                                <p class="text-sm text-zinc-500">
                                    By: {{ $order->deliveryNote->deliveredBy?->name ?? 'N/A' }}
                                </p>
                                @if ($order->deliveryNote->received_by_name)
                                    <p class="text-sm text-zinc-500">
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
                    </flux:card>
                @endif

                {{-- Payments Panel - ONLY if user can view payments --}}
                @if ($canViewPayments)
                    <livewire:orders.payments.panel :order="$order" wire:key="payments-panel-{{ $order->id }}" />
                @endif
            </div>

            {{-- Sidebar Info --}}
            <div class="space-y-6">
                {{-- Order Info --}}
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Order Info</flux:heading>

                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">Order Date</dt>
                            <dd class="text-zinc-900 dark:text-white">
                                {{ $order->order_date?->format('M d, Y') ?? $order->created_at->format('M d, Y') }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">Created By</dt>
                            <dd class="text-zinc-900 dark:text-white">{{ $order->creator?->name ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">Priority</dt>
                            <dd>
                                @php
                                    $priorityColor = match($order->priority) {
                                        \App\Enums\Priority::Low => 'zinc',
                                        \App\Enums\Priority::Normal => 'blue',
                                        \App\Enums\Priority::High => 'amber',
                                        \App\Enums\Priority::Urgent => 'red',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge color="{{ $priorityColor }}" size="sm">
                                    {{ $order->priority?->label() ?? 'N/A' }}
                                </flux:badge>
                            </dd>
                        </div>
                        {{-- Payment Status - ONLY if can view financials --}}
                        @if ($canViewFinancials)
                            <div class="flex justify-between">
                                <dt class="text-zinc-500 dark:text-zinc-400">Payment Status</dt>
                                <dd>
                                    @php
                                        $paymentColor = match($order->payment_status) {
                                            \App\Enums\PaymentStatus::Paid => 'green',
                                            \App\Enums\PaymentStatus::Partial => 'amber',
                                            \App\Enums\PaymentStatus::Unpaid => 'red',
                                            default => 'zinc',
                                        };
                                    @endphp
                                    <flux:badge color="{{ $paymentColor }}" size="sm">
                                        {{ $order->payment_status->label() }}
                                    </flux:badge>
                                </dd>
                            </div>
                        @endif
                        @if ($order->branch)
                            <div class="flex justify-between">
                                <dt class="text-zinc-500 dark:text-zinc-400">Branch</dt>
                                <dd class="text-zinc-900 dark:text-white">{{ $order->branch->name }}</dd>
                            </div>
                        @endif
                    </dl>
                </flux:card>

                {{-- Customer Info --}}
                @if ($order->customer)
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Customer</flux:heading>

                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-zinc-500 dark:text-zinc-400">Name</dt>
                                <dd class="text-zinc-900 dark:text-white">{{ $order->customer->name }}</dd>
                            </div>
                            @if ($order->customer->phone)
                                <div class="flex justify-between">
                                    <dt class="text-zinc-500 dark:text-zinc-400">Phone</dt>
                                    <dd class="text-zinc-900 dark:text-white">{{ $order->customer->phone }}</dd>
                                </div>
                            @endif
                            @if ($order->customer->email)
                                <div class="flex justify-between">
                                    <dt class="text-zinc-500 dark:text-zinc-400">Email</dt>
                                    <dd class="text-zinc-900 dark:text-white truncate max-w-[150px]">{{ $order->customer->email }}</dd>
                                </div>
                            @endif
                            @if ($order->customer->address)
                                <div>
                                    <dt class="text-zinc-500 dark:text-zinc-400">Address</dt>
                                    <dd class="mt-1 text-zinc-900 dark:text-white">{{ $order->customer->address }}</dd>
                                </div>
                            @endif
                        </dl>
                    </flux:card>
                @endif

                {{-- Notes --}}
                @if ($order->notes)
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Notes</flux:heading>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 whitespace-pre-wrap">{{ $order->notes }}</p>
                    </flux:card>
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
    </flux:main>
</div>

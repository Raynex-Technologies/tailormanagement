<div
    x-data="{
        draggingOrderId: null,
        draggingFromStatus: null,
        dropTarget: null,
        toasts: [],
        startDrag(event, orderId, fromStatus) {
            this.draggingOrderId = Number(orderId);
            this.draggingFromStatus = fromStatus;
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', JSON.stringify({
                    orderId: Number(orderId),
                    fromStatus: fromStatus,
                }));
            }
        },
        endDrag() {
            this.draggingOrderId = null;
            this.draggingFromStatus = null;
            this.dropTarget = null;
        },
        setDropTarget(status) {
            this.dropTarget = status;
        },
        drop(event, targetStatus) {
            event.preventDefault();

            let payload = null;
            const raw = event.dataTransfer ? event.dataTransfer.getData('text/plain') : '';

            if (raw) {
                try {
                    payload = JSON.parse(raw);
                } catch (e) {
                    payload = null;
                }
            }

            const orderId = Number(payload?.orderId ?? this.draggingOrderId ?? 0);
            const fromStatus = payload?.fromStatus ?? this.draggingFromStatus;

            this.endDrag();

            if (!orderId || !targetStatus || fromStatus === targetStatus) {
                return;
            }

            $wire.moveOrder(orderId, targetStatus);
        },
        toastClasses(variant) {
            const map = {
                success: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
                danger: 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300',
                warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                info: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            };

            return map[variant] ?? map.info;
        },
        showToast(variant, text) {
            if (!text) {
                return;
            }

            const toast = {
                id: Date.now() + Math.random(),
                variant: variant || 'info',
                text: text,
                visible: true,
            };

            this.toasts.push(toast);

            setTimeout(() => {
                const activeToast = this.toasts.find((item) => item.id === toast.id);
                if (activeToast) {
                    activeToast.visible = false;
                }
            }, 3000);

            setTimeout(() => {
                this.toasts = this.toasts.filter((item) => item.id !== toast.id);
            }, 3400);
        },
    }"
    x-on:board-toast.window="showToast($event.detail.variant, $event.detail.text)"
>
    <flux:main class="p-0">
        <div class="pointer-events-none fixed right-6 top-20 z-[120] w-80 space-y-2">
            <template x-for="toast in toasts" :key="toast.id">
                <div
                    x-show="toast.visible"
                    x-transition.opacity.duration.200ms
                    class="pointer-events-auto rounded-xl border px-4 py-3 text-sm font-medium shadow-lg"
                    :class="toastClasses(toast.variant)"
                >
                    <span x-text="toast.text"></span>
                </div>
            </template>
        </div>

        <div class="mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
                <flux:breadcrumbs.item :href="route('orders.index')" wire:navigate>{{ __('Orders') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Board') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        {{-- Page Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Order Board') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                    {{ __('Quick overview of order status across stages.') }}
                </flux:text>
            </div>

            @can('orders.create')
                <flux:button variant="primary" icon="plus" :href="route('orders.create')" wire:navigate>
                    {{ __('New Order') }}
                </flux:button>
            @endcan
        </div>

        {{-- Search --}}
        <div class="mb-6">
            <flux:input
                wire:model.blur="search"
                placeholder="Search by order no, customer name, or phone..."
                icon="magnifying-glass"
                class="max-w-md"
            />
        </div>

        {{-- Board Columns --}}
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- New Column --}}
            <div class="flex flex-col">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-blue-500"></div>
                        <flux:heading size="lg">New</flux:heading>
                        <flux:badge color="blue" size="sm">{{ $newCount }}</flux:badge>
                    </div>
                </div>

                <div
                    class="flex-1 space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 transition dark:border-zinc-700 dark:bg-zinc-800/50"
                    :class="dropTarget === 'new' ? 'ring-2 ring-indigo-300 dark:ring-indigo-600' : ''"
                    @dragenter.prevent="setDropTarget('new')"
                    @dragover.prevent="setDropTarget('new')"
                    @dragleave="dropTarget = null"
                    @drop="drop($event, 'new')"
                >
                    @forelse ($newOrders as $order)
                        @include('livewire.orders.partials.board-card', ['order' => $order])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <x-icon name="inbox" class="size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:text class="mt-2 text-zinc-500">No new orders</flux:text>
                        </div>
                    @endforelse

                    @if ($newHasMore)
                        <flux:button variant="ghost" class="w-full" wire:click="loadMoreNew">
                            Load More
                        </flux:button>
                    @endif
                </div>
            </div>

            {{-- In Progress Column --}}
            <div class="flex flex-col">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-amber-500"></div>
                        <flux:heading size="lg">In Progress</flux:heading>
                        <flux:badge color="amber" size="sm">{{ $inProgressCount }}</flux:badge>
                    </div>
                </div>

                <div
                    class="flex-1 space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 transition dark:border-zinc-700 dark:bg-zinc-800/50"
                    :class="dropTarget === 'in_progress' ? 'ring-2 ring-indigo-300 dark:ring-indigo-600' : ''"
                    @dragenter.prevent="setDropTarget('in_progress')"
                    @dragover.prevent="setDropTarget('in_progress')"
                    @dragleave="dropTarget = null"
                    @drop="drop($event, 'in_progress')"
                >
                    @forelse ($inProgressOrders as $order)
                        @include('livewire.orders.partials.board-card', ['order' => $order])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <x-icon name="inbox" class="size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:text class="mt-2 text-zinc-500">No orders in progress</flux:text>
                        </div>
                    @endforelse

                    @if ($inProgressHasMore)
                        <flux:button variant="ghost" class="w-full" wire:click="loadMoreInProgress">
                            Load More
                        </flux:button>
                    @endif
                </div>
            </div>

            {{-- Ready Column --}}
            <div class="flex flex-col">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-emerald-500"></div>
                        <flux:heading size="lg">Ready</flux:heading>
                        <flux:badge color="emerald" size="sm">{{ $readyCount }}</flux:badge>
                    </div>
                </div>

                <div
                    class="flex-1 space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 transition dark:border-zinc-700 dark:bg-zinc-800/50"
                    :class="dropTarget === 'ready' ? 'ring-2 ring-indigo-300 dark:ring-indigo-600' : ''"
                    @dragenter.prevent="setDropTarget('ready')"
                    @dragover.prevent="setDropTarget('ready')"
                    @dragleave="dropTarget = null"
                    @drop="drop($event, 'ready')"
                >
                    @forelse ($readyOrders as $order)
                        @include('livewire.orders.partials.board-card', ['order' => $order])
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <x-icon name="inbox" class="size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:text class="mt-2 text-zinc-500">No ready orders</flux:text>
                        </div>
                    @endforelse

                    @if ($readyHasMore)
                        <flux:button variant="ghost" class="w-full" wire:click="loadMoreReady">
                            Load More
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </flux:main>
</div>

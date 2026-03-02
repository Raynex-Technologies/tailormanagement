@php
    $statusColor = $order->status->color();
    $paymentColor = $order->payment_status->color();
    $hideComplete = $hideCompleteButton ?? false;
@endphp

<div class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800" wire:key="board-order-{{ $order->id }}">
    {{-- Header --}}
    <div class="flex items-start justify-between">
        <a href="{{ route('orders.show', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
            {{ $order->order_no }}
        </a>
        <flux:badge color="{{ $statusColor }}" size="sm">
            {{ $order->status->label() }}
        </flux:badge>
    </div>

    {{-- Customer --}}
    <div class="mt-2">
        <p class="font-medium text-zinc-900 dark:text-white">{{ $order->customer?->name ?? 'N/A' }}</p>
        <p class="text-sm text-zinc-500">{{ $order->customer?->phone }}</p>
    </div>

    {{-- Details --}}
    <div class="mt-3 flex items-center justify-between text-sm">
        <div class="flex items-center gap-2">
            <flux:badge color="{{ $paymentColor }}" size="sm">
                {{ $order->payment_status->label() }}
            </flux:badge>
            @if ($order->isOverdue())
                <flux:badge color="red" size="sm">Overdue</flux:badge>
            @endif
        </div>
        <span class="font-mono font-medium text-zinc-900 dark:text-white">
            {{ number_format($order->total, 0) }}
        </span>
    </div>

    {{-- Due Date --}}
    @if ($order->due_date)
        <div class="mt-2 text-xs text-zinc-500">
            Due: {{ $order->due_date->format('M d, Y') }}
        </div>
    @endif

    {{-- Actions --}}
    @if ($canMarkCompleted && !$hideComplete && $order->status === \App\Enums\OrderStatus::Delivered)
        <div class="mt-3 border-t border-zinc-100 pt-3 dark:border-zinc-700">
            <flux:button
                size="sm"
                variant="primary"
                class="w-full"
                wire:click="markCompleted({{ $order->id }})"
                wire:confirm="Are you sure you want to mark this order as completed?"
            >
                <x-icon name="check" class="mr-1 size-4" />
                Mark Completed
            </flux:button>
        </div>
    @endif
</div>

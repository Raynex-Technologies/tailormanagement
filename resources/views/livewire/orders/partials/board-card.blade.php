@php
    $priorityValue = $order->priority?->value ?? 'normal';
    $priorityLabel = $order->priority?->label() ?? ucfirst($priorityValue);
    $priorityBackground = match ($priorityValue) {
        'low' => '#fadfc0',
        'normal' => '#bef0db',
        'high' => '#d4d3fd',
        'urgent' => '#f3c5c5',
        default => '#bef0db',
    };
@endphp

<div
    class="group cursor-move rounded-xl border border-zinc-200 p-4 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-700"
    style="background-color: {{ $priorityBackground }};"
    wire:key="board-order-{{ $order->id }}"
    draggable="true"
    @dragstart="startDrag($event, {{ $order->id }}, '{{ $order->status->value }}')"
    @dragend="endDrag()"
>
    {{-- Header --}}
    <div class="flex items-start justify-between gap-3">
        <a href="{{ route('orders.show', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
            {{ $order->order_no }}
        </a>
        <span class="rounded-md bg-white/70 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-zinc-700">
            {{ $priorityLabel }}
        </span>
    </div>

    {{-- Customer --}}
    <div class="mt-2">
        <p class="font-medium text-zinc-900">{{ $order->customer?->name ?? 'N/A' }}</p>
        <p class="text-sm text-zinc-700">{{ $order->customer?->phone }}</p>
    </div>

    {{-- Details --}}
    <div class="mt-3 flex items-center justify-between text-sm">
        <div class="flex flex-col">
            <span class="font-medium text-zinc-800">{{ $order->status->label() }}</span>
            <span class="text-xs text-zinc-700">{{ $order->payment_status->label() }}</span>
        </div>
        <span class="font-mono font-medium text-zinc-900">
            {{ number_format($order->total, 0) }}
        </span>
    </div>

    {{-- Due Date --}}
    @if ($order->due_date)
        <div class="mt-2 text-xs text-zinc-700">
            Due: {{ $order->due_date->format('M d, Y') }}
        </div>
    @endif
</div>

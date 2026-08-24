@php($line = $displayLine['record'])

<div class="rounded-xl border border-zinc-200 border-l-4 border-l-lime-400 bg-zinc-50 p-4 dark:border-zinc-700 dark:border-l-lime-500 dark:bg-zinc-800/50" data-order-line>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <span class="font-medium text-zinc-900 dark:text-white">{{ $displayLine['display_name'] }}</span>
            @if ($line->notes)
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $line->notes }}</p>
            @endif
            @if ($line->assignedTailor?->name || $order->assignedTailor?->name)
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <i class="fa-duotone fa-user size-3 text-zinc-400 dark:text-zinc-500" aria-hidden="true"></i>
                    {{ __('Tailor: :tailor', ['tailor' => $line->assignedTailor?->name ?? $order->assignedTailor?->name]) }}
                </p>
            @endif
        </div>

        @if ($canViewFinancials)
            <div class="shrink-0 text-right">
                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.') }} &times; {{ number_format($line->unit_price, 0) }}
                </div>
                <div class="font-mono font-medium text-zinc-900 dark:text-white">
                    {{ number_format($line->line_total, 0) }}
                </div>
            </div>
        @else
            <div class="shrink-0 text-right text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Qty: :quantity', ['quantity' => rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.')]) }}
            </div>
        @endif
    </div>

    @if ($line->measurement && ! empty($line->measurement->measurements))
        <div class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <span class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">
                <i class="fa-duotone fa-ruler size-3 text-zinc-400 dark:text-zinc-500" aria-hidden="true"></i>
                {{ __('Measurements') }}
            </span>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($line->measurement->measurements as $key => $value)
                    <span class="inline-flex items-center rounded-full border border-blue-200/60 bg-blue-50 px-3 py-1 text-sm dark:border-blue-800/40 dark:bg-blue-900/20">
                        <span class="font-medium text-blue-700 dark:text-blue-300">{{ $key }}:</span>
                        <span class="ml-1 text-blue-600 dark:text-blue-400">{{ $value }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>

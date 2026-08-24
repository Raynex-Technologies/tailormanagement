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

    @php($measurementSnapshot = app(\App\Support\Orders\OrderMeasurementSnapshot::class)->present($line->measurement))
    @if ($measurementSnapshot['entries'] !== [])
        <div class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">
                    <i class="fa-duotone fa-ruler size-3 text-zinc-400 dark:text-zinc-500" aria-hidden="true"></i>
                    {{ __('Measurements') }}
                </span>
                @if ($measurementSnapshot['source'])
                    <span class="text-xs text-zinc-500">
                        {{ __('Based on customer measurements from :date', [
                            'date' => $measurementSnapshot['source']['measured_at']?->format('M d, Y') ?: __('an earlier revision'),
                        ]) }}
                    </span>
                @endif
            </div>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($measurementSnapshot['entries'] as $entry)
                    <div class="flex items-baseline justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/40">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $entry['label'] }}</span>
                        <span class="shrink-0 tabular-nums text-zinc-600 dark:text-zinc-300">
                            {{ $entry['value'] }}@if($entry['unit']) <span class="text-xs text-zinc-500">{{ $entry['unit'] }}</span>@endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

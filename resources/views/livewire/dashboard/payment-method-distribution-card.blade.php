<div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
    <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Payment Methods') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Distribution by amount') }}</p>
            </div>
            <div class="w-40">
                <flux:select wire:model.live="range" size="sm">
                    @foreach ($this->rangeOptions as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <div class="space-y-4 p-5">
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->distribution['period_label'] }}</p>

        <div class="relative mx-auto size-48">
            <div
                class="size-full rounded-full border border-zinc-200/70 dark:border-zinc-700/70"
                style="background: {{ $this->distribution['chart_gradient'] }};"
            ></div>
            <div class="absolute inset-6 rounded-full bg-white dark:bg-zinc-900/95 flex flex-col items-center justify-center text-center shadow-inner">
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</p>
                <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ number_format($this->distribution['total_amount'], 0) }}</p>
                <p class="text-[11px] text-zinc-400 dark:text-zinc-500">TZS</p>
            </div>
        </div>

        @if ($this->distribution['has_data'])
            <div class="space-y-2">
                @foreach ($this->distribution['items'] as $item)
                    <div class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 bg-zinc-50/70 dark:bg-zinc-800/70">
                        <div class="min-w-0 flex items-center gap-2">
                            <span class="size-2.5 rounded-full shrink-0" style="background-color: {{ $item['color'] }};"></span>
                            <span class="truncate text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $item['name'] }}</span>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($item['percentage'], 1) }}%</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($item['amount'], 0) }} TZS</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-2 text-xs text-zinc-500 dark:text-zinc-400">
                {{ number_format($this->distribution['total_payments']) }} {{ __('payments') }}
            </div>
        @else
            <div class="py-6 text-center">
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('No payment data') }}</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No payments were recorded in this period.') }}</p>
            </div>
        @endif
    </div>
</div>

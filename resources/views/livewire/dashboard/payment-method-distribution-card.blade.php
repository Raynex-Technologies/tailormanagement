<div class="rounded-2xl bg-white dark:bg-zinc-800/50 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
    <div class="border-b border-zinc-100 dark:border-zinc-700/50 px-5 py-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Payment Methods') }}</h2>
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

    <div
        class="space-y-4 p-5"
        x-data="{
            hoveredIndex: null,
            items: @js($this->distribution['items']),
            totalAmount: {{ (float) $this->distribution['total_amount'] }},
            totalLabel: @js(__('Total')),
            shareLabel: @js(__('share')),
            activeItem() {
                return this.hoveredIndex === null ? null : (this.items[this.hoveredIndex] ?? null);
            },
            centerLabel() {
                const item = this.activeItem();
                return item ? item.name : this.totalLabel;
            },
            centerAmount() {
                const item = this.activeItem();
                return item ? Number(item.amount ?? 0) : this.totalAmount;
            },
            centerMeta() {
                const item = this.activeItem();
                if (!item) {
                    return 'TZS';
                }

                const share = Number(item.percentage ?? 0);
                return `${Number.isFinite(share) ? share.toFixed(1) : '0.0'}% ${this.shareLabel}`;
            },
            formatAmount(value) {
                const number = Number(value ?? 0);
                if (!Number.isFinite(number)) {
                    return '0';
                }

                return number.toLocaleString(undefined, {
                    maximumFractionDigits: 0
                });
            }
        }"
    >
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->distribution['period_label'] }}</p>

        @php
            $chartSize = 160;
            $chartCenter = $chartSize / 2;
            $chartRadius = 52;
            $baseStrokeWidth = 22;
            $hoverStrokeWidth = 27;
            $innerRadius = $chartRadius - ($baseStrokeWidth / 2);
            $innerInsetPercent = (($chartCenter - $innerRadius) / $chartSize) * 100;
        @endphp

        @if ($this->distribution['has_data'])
            @php
                $circumference = 2 * pi() * $chartRadius;
                $runningLength = 0.0;
                $segmentMeta = [];

                foreach ($this->distribution['items'] as $index => $item) {
                    $amount = (float) ($item['amount'] ?? 0);
                    $percentExact = (float) $this->distribution['total_amount'] > 0
                        ? max(0.0, ($amount / (float) $this->distribution['total_amount']) * 100)
                        : 0.0;
                    $segmentLength = ($percentExact / 100) * $circumference;
                    $midAngle = (($runningLength + ($segmentLength / 2)) / $circumference) * 360;
                    $midRadian = deg2rad($midAngle - 90);

                    $segmentMeta[$index] = [
                        'length' => $segmentLength,
                        'offset' => $runningLength,
                        'translate_x' => round(cos($midRadian) * 4, 3),
                        'translate_y' => round(sin($midRadian) * 4, 3),
                    ];

                    $runningLength += $segmentLength;
                }
            @endphp

            <div class="relative mx-auto size-56">
                <svg viewBox="0 0 {{ $chartSize }} {{ $chartSize }}" class="size-full" role="img" aria-label="{{ __('Payment method distribution chart') }}">
                    <circle
                        cx="{{ $chartCenter }}"
                        cy="{{ $chartCenter }}"
                        r="{{ $chartRadius }}"
                        fill="none"
                        stroke-width="{{ $baseStrokeWidth }}"
                        class="stroke-zinc-200/80 dark:stroke-zinc-700/80"
                    />

                    @foreach ($this->distribution['items'] as $index => $item)
                        @php
                            $segment = $segmentMeta[$index] ?? ['length' => 0, 'offset' => 0, 'translate_x' => 0, 'translate_y' => 0];
                        @endphp
                        @continue(($segment['length'] ?? 0) <= 0)

                        <circle
                            cx="{{ $chartCenter }}"
                            cy="{{ $chartCenter }}"
                            r="{{ $chartRadius }}"
                            fill="none"
                            stroke="{{ $item['color'] }}"
                            stroke-linecap="butt"
                            stroke-dasharray="{{ number_format((float) $segment['length'], 4, '.', '') }} {{ number_format($circumference, 4, '.', '') }}"
                            stroke-dashoffset="-{{ number_format((float) $segment['offset'], 4, '.', '') }}"
                            class="cursor-pointer transition-all duration-200 ease-out"
                            x-bind:opacity="hoveredIndex === null || hoveredIndex === {{ $index }} ? 1 : 0.38"
                            x-bind:stroke-width="hoveredIndex === {{ $index }} ? {{ $hoverStrokeWidth }} : {{ $baseStrokeWidth }}"
                            x-bind:style="{
                                transform: hoveredIndex === {{ $index }} ? 'translate({{ $segment['translate_x'] }}px, {{ $segment['translate_y'] }}px)' : 'translate(0px, 0px)',
                                filter: hoveredIndex === {{ $index }} ? 'drop-shadow(0 6px 12px rgba(15, 23, 42, 0.25))' : 'none',
                                transformBox: 'fill-box',
                                transformOrigin: 'center'
                            }"
                            @mouseenter="hoveredIndex = {{ $index }}"
                            @mouseleave="hoveredIndex = null"
                            @focus="hoveredIndex = {{ $index }}"
                            @blur="hoveredIndex = null"
                            tabindex="0"
                        />
                    @endforeach
                </svg>

                <div class="absolute rounded-full bg-white dark:bg-zinc-900/95 flex flex-col items-center justify-center text-center shadow-inner px-2" style="inset: {{ rtrim(rtrim(number_format($innerInsetPercent, 3, '.', ''), '0'), '.') }}%;">
                    <p class="max-w-[8.5rem] truncate text-[11px] text-zinc-500 dark:text-zinc-400" x-text="centerLabel()"></p>
                    <p class="text-base font-bold text-zinc-900 dark:text-white" x-text="formatAmount(centerAmount())"></p>
                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500" x-text="centerMeta()"></p>
                </div>
            </div>
        @else
            <div class="relative mx-auto size-56">
                <div
                    class="size-full rounded-full border border-zinc-200/70 dark:border-zinc-700/70"
                    style="background: {{ $this->distribution['chart_gradient'] }};"
                ></div>
                <div class="absolute rounded-full bg-white dark:bg-zinc-900/95 flex flex-col items-center justify-center text-center shadow-inner" style="inset: {{ rtrim(rtrim(number_format($innerInsetPercent, 3, '.', ''), '0'), '.') }}%;">
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</p>
                    <p class="text-base font-bold text-zinc-900 dark:text-white">{{ number_format($this->distribution['total_amount'], 0) }}</p>
                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500">TZS</p>
                </div>
            </div>
        @endif

        @if ($this->distribution['has_data'])
            <div class="space-y-2">
                @foreach ($this->distribution['items'] as $index => $item)
                    <div
                        class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 bg-zinc-50 dark:bg-zinc-800/70 border border-zinc-100 dark:border-zinc-700/50 transition-all duration-150"
                        x-bind:class="hoveredIndex === {{ $index }} ? 'ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 shadow-sm' : ''"
                        @mouseenter="hoveredIndex = {{ $index }}"
                        @mouseleave="hoveredIndex = null"
                    >
                        <div class="min-w-0 flex items-center gap-2.5">
                            <span class="size-2.5 rounded-full shrink-0" style="background-color: {{ $item['color'] }};"></span>
                            <span class="truncate text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $item['name'] }}</span>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ number_format($item['percentage'], 1) }}%</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($item['amount'], 0) }} TZS</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 pt-3 border-t border-zinc-100 dark:border-zinc-700/50 text-xs text-zinc-500 dark:text-zinc-400">
                {{ number_format($this->distribution['total_payments']) }} {{ __('payments') }}
            </div>
        @else
            <div class="py-8 text-center">
                <div class="flex items-center justify-center size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 mx-auto mb-3">
                    <i class="fa-duotone fa-credit-card size-5 text-zinc-400 dark:text-zinc-500"></i>
                </div>
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('No payment data') }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('No payments recorded in this period.') }}</p>
            </div>
        @endif
    </div>
</div>

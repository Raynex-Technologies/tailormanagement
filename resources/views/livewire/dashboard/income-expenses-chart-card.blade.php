<div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Income vs Expenses') }}</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Trend for selected period') }} - {{ $this->chartData['period_label'] }}</p>
        </div>

        <div class="w-full sm:w-44">
            <flux:select wire:model.live="range" size="sm">
                @foreach ($this->rangeOptions as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-emerald-200/70 dark:border-emerald-800/60 bg-emerald-50/70 dark:bg-emerald-900/20 px-4 py-3">
            <p class="text-xs font-medium text-emerald-700 dark:text-emerald-300">{{ __('Income') }}</p>
            <p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-300">{{ number_format($this->chartData['income_total'], 0) }} <span class="text-xs font-normal">TZS</span></p>
        </div>
        <div class="rounded-xl border border-red-200/70 dark:border-red-800/60 bg-red-50/70 dark:bg-red-900/20 px-4 py-3">
            <p class="text-xs font-medium text-red-700 dark:text-red-300">{{ __('Expenses') }}</p>
            <p class="mt-1 text-lg font-bold text-red-700 dark:text-red-300">{{ number_format($this->chartData['expense_total'], 0) }} <span class="text-xs font-normal">TZS</span></p>
        </div>
        <div class="rounded-xl border border-zinc-200/70 dark:border-zinc-700/70 bg-zinc-50/70 dark:bg-zinc-900/40 px-4 py-3">
            <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Net') }}</p>
            <p class="mt-1 text-lg font-bold {{ $this->chartData['net_total'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                {{ number_format($this->chartData['net_total'], 0) }} <span class="text-xs font-normal">TZS</span>
            </p>
        </div>
    </div>

    @if ($this->chartData['requires_branch_selection'])
        <div class="mt-4 rounded-xl border border-amber-200/60 dark:border-amber-800/60 bg-amber-50/70 dark:bg-amber-900/20 p-4">
            <p class="text-sm font-medium text-amber-700 dark:text-amber-300">{{ __('Select a branch to view income and expense trends.') }}</p>
        </div>
    @else
        <div
            wire:key="{{ $this->chartKey }}"
            class="mt-4"
            x-data="{
                chart: null,
                payload: @js($this->chartData),
                init() {
                    this.ensureApex(() => this.renderChart());
                },
                ensureApex(done) {
                    if (window.ApexCharts) {
                        done();
                        return;
                    }

                    const existing = document.querySelector('script[data-apexcharts-loader=dashboard]');
                    if (existing) {
                        existing.addEventListener('load', done, { once: true });
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
                    script.dataset.apexchartsLoader = 'dashboard';
                    script.addEventListener('load', done, { once: true });
                    document.head.appendChild(script);
                },
                renderChart() {
                    if (!window.ApexCharts || !this.$refs.canvas) {
                        return;
                    }

                    this.destroyChart();

                    const options = {
                        chart: {
                            type: 'area',
                            height: 320,
                            toolbar: { show: false },
                            zoom: { enabled: false },
                            fontFamily: 'DM Sans, sans-serif'
                        },
                        series: this.payload.series,
                        colors: ['#22C55E', '#EF4444'],
                        stroke: {
                            curve: 'smooth',
                            width: 3
                        },
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shadeIntensity: 1,
                                opacityFrom: 0.35,
                                opacityTo: 0.05,
                                stops: [0, 90, 100]
                            }
                        },
                        dataLabels: { enabled: false },
                        grid: {
                            borderColor: '#E5E7EB',
                            strokeDashArray: 4
                        },
                        xaxis: {
                            categories: this.payload.categories,
                            labels: {
                                style: { colors: '#6B7280' }
                            }
                        },
                        yaxis: {
                            labels: {
                                style: { colors: '#6B7280' },
                                formatter: (value) => Number(value).toLocaleString()
                            }
                        },
                        legend: {
                            position: 'top',
                            horizontalAlign: 'right'
                        },
                        tooltip: {
                            y: {
                                formatter: (value) => `${Number(value).toLocaleString()} TZS`
                            }
                        },
                        noData: { text: @js(__('No data available')) }
                    };

                    this.chart = new ApexCharts(this.$refs.canvas, options);
                    this.chart.render();
                },
                destroyChart() {
                    if (!this.chart) {
                        return;
                    }

                    this.chart.destroy();
                    this.chart = null;
                }
            }"
            x-on:livewire:navigating.window="destroyChart()"
        >
            <div x-ref="canvas" class="h-80"></div>
        </div>

        @if (! $this->chartData['has_data'])
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No income or expenses recorded in this period.') }}</p>
        @endif
    @endif
</div>

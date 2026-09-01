<div x-data="{ filtersOpen: false }">
    <flux:main class="p-0">
        <section
            class="mb-6 overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:p-6"
            style="background: linear-gradient(135deg, var(--tailorpro-primary) 0%, color-mix(in srgb, var(--tailorpro-primary) 88%, #ffffff 12%) 100%);"
            data-invoices-workspace-header
        >
            <flux:breadcrumbs class="mb-5 text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item class="!text-white">{{ __('Invoices') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl" class="!text-white">{{ __('Invoices') }}</flux:heading>
                <p class="mt-1 text-sm text-white/70">{{ __('View, track and manage customer invoices.') }}</p>
            </div>
        </section>

        @if ($canViewKpis)
            @php
                $compactValue = static function (float|int $value, bool $money = false): string {
                    $absolute = abs((float) $value);
                    $formatted = number_format($value, 0);

                    foreach ([[1_000_000_000, 'B'], [1_000_000, 'M'], [1_000, 'K']] as [$threshold, $suffix]) {
                        if ($absolute >= $threshold) {
                            $formatted = rtrim(rtrim(number_format(round($value / $threshold, 1), 1, '.', ''), '0'), '.').$suffix;
                            break;
                        }
                    }

                    return $money ? 'Tsh '.$formatted : $formatted;
                };
                $kpiCards = [
                    ['key' => 'invoices', 'label' => __('Invoices'), 'icon' => 'fa-file-invoice', 'money' => false, 'color' => 'text-sky-600 bg-sky-50 dark:bg-sky-950/50 dark:text-sky-300'],
                    ['key' => 'amount', 'label' => __('Invoiced Amount'), 'icon' => 'fa-coins', 'money' => true, 'color' => 'text-violet-600 bg-violet-50 dark:bg-violet-950/50 dark:text-violet-300'],
                    ['key' => 'paid', 'label' => __('Paid'), 'icon' => 'fa-circle-check', 'money' => true, 'color' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/50 dark:text-emerald-300'],
                    ['key' => 'outstanding', 'label' => __('Outstanding'), 'icon' => 'fa-scale-balanced', 'money' => true, 'color' => 'text-amber-600 bg-amber-50 dark:bg-amber-950/50 dark:text-amber-300'],
                ];
            @endphp

            <section class="mb-4" aria-labelledby="invoice-kpis-heading" data-invoice-kpis>
                <div class="mb-3">
                    <h2 id="invoice-kpis-heading" class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Invoice Overview') }}</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $kpiPeriodLabel }} · {{ __('compared with the previous month') }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($kpiCards as $card)
                        @php
                            $metric = $kpis[$card['key']];
                            $growth = (float) $metric['growth'];
                            $fullValue = $card['money'] ? money_tzs($metric['value']) : number_format($metric['value']);
                        @endphp
                        <flux:card class="relative overflow-hidden" data-invoice-kpi-card="{{ $card['key'] }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                                    <flux:tooltip :content="$fullValue" position="top">
                                        <p class="mt-2 cursor-help text-2xl font-bold tracking-tight text-zinc-950 dark:text-white" aria-label="{{ $card['label'] }}: {{ $fullValue }}">
                                            {{ $compactValue($metric['value'], $card['money']) }}
                                        </p>
                                    </flux:tooltip>
                                </div>
                                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl {{ $card['color'] }}">
                                    <i class="fa-duotone {{ $card['icon'] }}" aria-hidden="true"></i>
                                </span>
                            </div>
                            <div class="mt-4 flex items-center gap-1.5 text-xs">
                                <span class="inline-flex items-center gap-1 font-semibold {{ $growth > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($growth < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-500') }}">
                                    <i class="fa-solid {{ $growth > 0 ? 'fa-arrow-trend-up' : ($growth < 0 ? 'fa-arrow-trend-down' : 'fa-minus') }}" aria-hidden="true"></i>
                                    {{ number_format(abs($growth), 1) }}%
                                </span>
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('vs previous month') }}</span>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            </section>
        @endif

        @if (session('success'))
            <flux:callout class="mb-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if (session('error'))
            <flux:callout class="mb-4" variant="danger" icon="exclamation-circle">
                {{ session('error') }}
            </flux:callout>
        @endif

        <div class="mb-4 flex justify-end">
            <flux:button
                variant="ghost"
                icon="funnel"
                x-on:click="filtersOpen = ! filtersOpen"
                x-bind:aria-expanded="filtersOpen"
                aria-controls="invoice-filters"
            >
                {{ __('Filters') }}
            </flux:button>
        </div>

        <flux:card id="invoice-filters" x-show="filtersOpen" x-collapse x-cloak class="mb-6" wire:key="invoice-filter-panel">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search invoice, order, customer...') }}"
                    icon="magnifying-glass"
                    class="sm:col-span-2 xl:col-span-2"
                />
                <flux:input wire:model.live="dateFrom" type="date" label="{{ __('From date') }}" />
                <flux:input wire:model.live="dateTo" type="date" label="{{ __('To date') }}" />
                <flux:select wire:model.live="perPage" label="{{ __('Rows') }}">
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="15">15</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>
                <div class="flex items-end">
                    <flux:button class="w-full" variant="ghost" icon="x-mark" wire:click="clearFilters">
                        {{ __('Clear') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <flux:card data-invoice-list>
            <div class="overflow-x-auto custom-scrollbar-light">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr class="text-left text-sm font-semibold text-zinc-900 dark:text-white">
                            <th class="px-4 py-3">{{ __('Invoice') }}</th>
                            <th class="px-4 py-3">{{ __('Order') }}</th>
                            <th class="px-4 py-3">{{ __('Customer') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Paid') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Balance') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($invoices as $invoice)
                            @php
                                $order = $invoice->order;
                                $financialSummary = $order?->financialSummary() ?? [
                                    'paid' => 0,
                                    'balance' => (float) $invoice->total,
                                    'status' => null,
                                ];
                            @endphp
                            <tr class="text-sm text-zinc-700 dark:text-zinc-300" wire:key="invoice-{{ $invoice->id }}">
                                <td class="px-4 py-3">
                                    <div class="flex min-w-0 flex-col">
                                        <a
                                            href="{{ route('invoices.show', $invoice) }}"
                                            wire:navigate
                                            class="w-fit font-semibold text-indigo-600 underline-offset-4 transition hover:text-indigo-800 hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                            data-invoice-number-link
                                        >
                                            {{ $invoice->invoice_no }}
                                        </a>
                                        <span class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $invoice->branch?->name ?? __('No branch assigned') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">
                                    {{ $order?->order_no ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex min-w-0 flex-col">
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ $order?->customer?->name ?? 'N/A' }}</span>
                                        @if ($order?->customer?->phone)
                                            <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $order->customer->phone }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-zinc-900 dark:text-white">
                                    {{ money_tzs($invoice->total) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                                    {{ money_tzs($financialSummary['paid']) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-zinc-900 dark:text-white">
                                    {{ money_tzs($financialSummary['balance']) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($financialSummary['status'])
                                        <flux:badge color="{{ $financialSummary['status']->color() }}">
                                            {{ $financialSummary['status']->label() }}
                                        </flux:badge>
                                    @else
                                        <span class="text-zinc-500">N/A</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end" data-invoice-row-actions>
                                        <a
                                            href="{{ route('invoices.print', $invoice) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex min-h-9 items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                                            aria-label="{{ __('Print invoice :invoice', ['invoice' => $invoice->invoice_no]) }}"
                                            data-invoice-print-action
                                        >
                                            <i class="fa-duotone fa-print" aria-hidden="true"></i>
                                            {{ __('Print') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-zinc-500">
                                        <i class="fa-duotone fa-file-circle-xmark text-4xl text-zinc-300 dark:text-zinc-600" aria-hidden="true"></i>
                                        <p class="mt-4 font-medium text-zinc-700 dark:text-zinc-300">{{ __('No invoices found.') }}</p>
                                        <p class="mt-1 text-sm">{{ __('Try changing the search or reporting period.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="mt-4 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $invoices->links() }}
                </div>
            @endif
        </flux:card>
    </flux:main>
</div>

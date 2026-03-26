@php
    $visibleInvoices = $invoices->getCollection();
    $pageTotal = $visibleInvoices->sum('total');
    $sentCount = $visibleInvoices->filter(fn ($invoice) => filled($invoice->sent_at))->count();
@endphp

<div>
    <flux:main class="space-y-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Invoices') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-lime-100 text-lime-700 dark:bg-lime-500/15 dark:text-lime-300">
                        <i class="fa-duotone fa-file-invoice text-lg" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="xl">{{ __('Invoices') }}</flux:heading>
                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                            {{ __('Manage generated order invoices, then open or print any invoice directly from the list.') }}
                        </flux:text>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <div class="flex items-center gap-3">
                            <i class="fa-duotone fa-files text-zinc-400" aria-hidden="true"></i>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">{{ __('On Page') }}</p>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($visibleInvoices->count()) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <div class="flex items-center gap-3">
                            <i class="fa-duotone fa-wallet text-zinc-400" aria-hidden="true"></i>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">{{ __('Page Total') }}</p>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ money_tzs($pageTotal) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <div class="flex items-center gap-3">
                            <i class="fa-duotone fa-paper-plane text-zinc-400" aria-hidden="true"></i>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">{{ __('Sent') }}</p>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($sentCount) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-circle">
                {{ session('error') }}
            </flux:callout>
        @endif

        <flux:card>
            <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search invoice no, order no, customer...') }}"
                    icon="magnifying-glass"
                    class="sm:col-span-2 lg:col-span-3"
                />
                <flux:select wire:model.blur="perPage" label="{{ __('Rows') }}">
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="15">15</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Invoice') }}</flux:table.column>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Issue Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Due Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Total') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($invoices as $invoice)
                        <flux:table.row wire:key="invoice-{{ $invoice->id }}">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 items-center justify-center rounded-xl bg-lime-100 text-lime-700 dark:bg-lime-500/15 dark:text-lime-300">
                                        <i class="fa-duotone fa-file-invoice" aria-hidden="true"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-zinc-900 dark:text-white">{{ $invoice->invoice_no }}</div>
                                        <div class="truncate text-xs text-zinc-500">
                                            {{ $invoice->branch?->name ?? __('No branch assigned') }}
                                        </div>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="text-sm">
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $invoice->order?->order_no ?? 'N/A' }}</div>
                                    <div class="text-zinc-500">{{ __('Generated from order') }}</div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="text-sm">
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $invoice->order?->customer?->name ?? 'N/A' }}</div>
                                    @if ($invoice->order?->customer?->phone)
                                        <div class="text-zinc-500">{{ $invoice->order->customer->phone }}</div>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ optional($invoice->issue_date)->format('M d, Y') }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="text-sm">
                                    <div class="text-zinc-900 dark:text-white">{{ optional($invoice->due_date)->format('M d, Y') ?: 'N/A' }}</div>
                                    @if ($invoice->due_date && $invoice->due_date->isPast() && ($invoice->order?->balance_due ?? 0) > 0)
                                        <div class="text-xs font-medium text-red-600 dark:text-red-400">{{ __('Overdue') }}</div>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="font-mono font-semibold text-zinc-900 dark:text-white">
                                {{ money_tzs($invoice->total) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="{{ route('invoices.show', $invoice) }}"
                                        wire:navigate
                                        class="inline-flex items-center gap-2 rounded-lg bg-zinc-100 px-3 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-lime-100 hover:text-lime-900 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-lime-500/15 dark:hover:text-lime-200"
                                    >
                                        <i class="fa-duotone fa-folder-open" aria-hidden="true"></i>
                                        
                                    </a>
                                    <a
                                        href="{{ route('invoices.print', $invoice) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-xs font-semibold text-zinc-600 transition hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800"
                                    >
                                        <i class="fa-duotone fa-print" aria-hidden="true"></i>
                                        
                                    </a>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-zinc-500">
                                    <i class="fa-duotone fa-file-circle-xmark text-4xl text-zinc-300 dark:text-zinc-600" aria-hidden="true"></i>
                                    <p class="mt-4 font-medium text-zinc-700 dark:text-zinc-300">{{ __('No invoices found.') }}</p>
                                    <p class="mt-1 text-sm">{{ __('Try changing the search term or rows per page.') }}</p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
        </flux:card>
    </flux:main>
</div>

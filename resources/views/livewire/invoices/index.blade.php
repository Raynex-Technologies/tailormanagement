<div>
    <flux:main class="p-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Invoices') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <flux:heading size="xl">{{ __('Invoices') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Manage generated order invoices and open any invoice to edit details or send by email.') }}
                </flux:text>
            </div>
        </div>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if (session('error'))
            <flux:callout class="mt-4" variant="danger" icon="exclamation-circle">
                {{ session('error') }}
            </flux:callout>
        @endif

        <flux:card class="mt-6">
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
                    <flux:table.column>{{ __('Invoice No') }}</flux:table.column>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Issue Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Due Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Total') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($invoices as $invoice)
                        <flux:table.row wire:key="invoice-{{ $invoice->id }}">
                            <flux:table.cell class="font-medium">{{ $invoice->invoice_no }}</flux:table.cell>
                            <flux:table.cell>
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $invoice->order?->order_no ?? 'N/A' }}</span>
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
                            <flux:table.cell>{{ optional($invoice->due_date)->format('M d, Y') ?: 'N/A' }}</flux:table.cell>
                            <flux:table.cell class="font-mono font-semibold">{{ number_format($invoice->total, 0) }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-2">
                                    <flux:button size="xs" variant="subtle" :href="route('invoices.show', $invoice)" wire:navigate>
                                        {{ __('Open') }}
                                    </flux:button>
                                    <flux:button size="xs" variant="ghost" :href="route('invoices.print', $invoice)" target="_blank">
                                        {{ __('Print') }}
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">
                                {{ __('No invoices found.') }}
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

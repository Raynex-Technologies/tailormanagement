@php
    $paymentStatus = $invoice->order?->payment_status;
    $balanceDue = $invoice->order?->balance_due ?? 0;
    $isOverdue = $invoice->due_date && $invoice->due_date->isPast() && $balanceDue > 0;
@endphp

<div>
    <flux:main class="space-y-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('invoices.index')" wire:navigate>{{ __('Invoices') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $invoice->invoice_no }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-circle">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        <flux:card class="overflow-hidden">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="flex size-14 items-center justify-center rounded-2xl bg-lime-100 text-lime-700 shadow-sm dark:bg-lime-500/15 dark:text-lime-300">
                        <i class="fa-duotone fa-file-invoice text-xl" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="xl">{{ $invoice->invoice_no }}</flux:heading>
                            @if ($paymentStatus)
                                <flux:badge color="{{ $paymentStatus->color() }}" size="sm">
                                    {{ $paymentStatus->label() }}
                                </flux:badge>
                            @endif
                            @if ($invoice->sent_at)
                                <flux:badge color="green" size="sm">{{ __('Sent') }}</flux:badge>
                            @endif
                            @if ($isOverdue)
                                <flux:badge color="red" size="sm">{{ __('Overdue') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                            {{ __('Order') }}: {{ $invoice->order?->order_no ?? 'N/A' }}
                            @if ($invoice->order?->customer?->name)
                                <span class="mx-2 text-zinc-300 dark:text-zinc-600">&bull;</span>
                                {{ $invoice->order->customer->name }}
                            @endif
                        </flux:text>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 sm:justify-end">
                    <flux:button size="sm" variant="subtle" :href="route('invoices.print', $invoice)" target="_blank">
                        <i class="fa-duotone fa-print mr-1.5" aria-hidden="true"></i>
                        {{ __('Print') }}
                    </flux:button>
                    <flux:button size="sm" variant="subtle" :href="route('invoices.download', $invoice)">
                        <i class="fa-duotone fa-download mr-1.5" aria-hidden="true"></i>
                        {{ __('Download') }}
                    </flux:button>
                    @if (! $isEditing)
                        @can('update', $invoice)
                            <flux:button size="sm" variant="primary" wire:click="startEditing">
                                <i class="fa-duotone fa-pen-to-square mr-1.5" aria-hidden="true"></i>
                                {{ __('Edit Invoice') }}
                            </flux:button>
                        @endcan
                    @endif
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                    <div class="flex items-center gap-3">
                        <i class="fa-duotone fa-calendar text-zinc-400" aria-hidden="true"></i>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">{{ __('Issue Date') }}</p>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ optional($invoice->issue_date)->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                    <div class="flex items-center gap-3">
                        <i class="fa-duotone fa-calendar-clock text-zinc-400" aria-hidden="true"></i>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">{{ __('Due Date') }}</p>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ optional($invoice->due_date)->format('M d, Y') ?: 'N/A' }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                    <div class="flex items-center gap-3">
                        <i class="fa-duotone fa-wallet text-zinc-400" aria-hidden="true"></i>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">{{ __('Invoice Total') }}</p>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ money_tzs($invoice->total) }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                    <div class="flex items-center gap-3">
                        <i class="fa-duotone fa-scale-balanced text-zinc-400" aria-hidden="true"></i>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">{{ __('Balance Due') }}</p>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ money_tzs($balanceDue) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <flux:card>
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            <i class="fa-duotone fa-file-lines" aria-hidden="true"></i>
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('Invoice Details') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Update dates, discount, and notes when invoice edits are allowed.') }}</flux:text>
                        </div>
                    </div>

                    <div class="mb-4 grid gap-4 sm:grid-cols-3">
                        <flux:input wire:model="issue_date" type="date" label="{{ __('Issue Date') }}" :disabled="!$isEditing" />
                        <flux:input wire:model="due_date" type="date" label="{{ __('Due Date') }}" :disabled="!$isEditing" />
                        <flux:input wire:model.live="discount" type="number" min="0" step="1" label="{{ __('Discount') }}" :disabled="!$isEditing" />
                    </div>

                    <flux:textarea
                        wire:model="notes"
                        label="{{ __('Notes') }}"
                        rows="3"
                        :disabled="!$isEditing"
                    />
                </flux:card>

                <flux:card>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                <i class="fa-duotone fa-rectangle-list" aria-hidden="true"></i>
                            </div>
                            <div>
                                <flux:heading size="lg">{{ __('Invoice Items') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Review line items and recalculate totals instantly while editing.') }}</flux:text>
                            </div>
                        </div>
                        @if ($isEditing)
                            <flux:button size="sm" variant="subtle" type="button" wire:click="addLine">
                                <i class="fa-duotone fa-plus mr-1.5" aria-hidden="true"></i>
                                {{ __('Add Item') }}
                            </flux:button>
                        @endif
                    </div>

                    <div class="space-y-4">
                        @foreach ($lines as $index => $line)
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50" wire:key="invoice-line-{{ $index }}">
                                <div class="mb-3 flex items-start justify-between">
                                    <flux:badge size="sm">{{ __('Item') }} {{ $index + 1 }}</flux:badge>
                                    @if ($isEditing && count($lines) > 1)
                                        <flux:button size="xs" variant="ghost" type="button" wire:click="removeLine({{ $index }})">
                                            <i class="fa-duotone fa-trash-can text-red-500" aria-hidden="true"></i>
                                        </flux:button>
                                    @endif
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                    <div class="lg:col-span-2">
                                        <flux:input
                                            wire:model="lines.{{ $index }}.item_name"
                                            label="{{ __('Item Name') }}"
                                            :disabled="!$isEditing"
                                        />
                                    </div>
                                    <flux:input
                                        wire:model.live="lines.{{ $index }}.qty"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        label="{{ __('Qty') }}"
                                        :disabled="!$isEditing"
                                    />
                                    <flux:input
                                        wire:model.live="lines.{{ $index }}.unit_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        label="{{ __('Unit Price') }}"
                                        :disabled="!$isEditing"
                                    />
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Line Total') }}</label>
                                        <div class="flex h-10 items-center rounded-lg bg-zinc-100 px-3 font-mono dark:bg-zinc-700">
                                            {{ number_format($lines[$index]['line_total'] ?? 0, 0) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <flux:input
                                        wire:model="lines.{{ $index }}.notes"
                                        label="{{ __('Notes') }}"
                                        :disabled="!$isEditing"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex justify-end">
                        <div class="w-full max-w-sm space-y-2 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-500">{{ __('Subtotal') }}</span>
                                <span class="font-mono">{{ money_tzs($subtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-500">{{ __('Discount') }}</span>
                                <span class="font-mono text-red-600">-{{ money_tzs($discount ?? 0, false) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-zinc-200 pt-2 text-lg font-semibold dark:border-zinc-700">
                                <span>{{ __('Total') }}</span>
                                <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ money_tzs($total) }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($isEditing)
                        <div class="mt-6 flex justify-end gap-2">
                            <flux:button variant="ghost" wire:click="cancelEditing" type="button">
                                <i class="fa-duotone fa-xmark mr-1.5" aria-hidden="true"></i>
                                {{ __('Cancel') }}
                            </flux:button>
                            <flux:button variant="primary" wire:click="save" type="button">
                                <i class="fa-duotone fa-floppy-disk mr-1.5" aria-hidden="true"></i>
                                {{ __('Save Invoice') }}
                            </flux:button>
                        </div>
                    @endif
                </flux:card>
            </div>

            <div class="space-y-6">
                <flux:card>
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            <i class="fa-duotone fa-building" aria-hidden="true"></i>
                        </div>
                        <flux:heading size="lg">{{ __('Business Details') }}</flux:heading>
                    </div>

                    @if ($settings->logo_url)
                        <img src="{{ $settings->logo_url }}" alt="{{ __('Business Logo') }}" class="mb-3 h-16 w-auto rounded-md border border-zinc-200 p-1 dark:border-zinc-700" />
                    @endif

                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-zinc-500">{{ __('Name') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-white">{{ $settings->business_name ?: config('app.name', 'Tailoring Business') }}</dd>
                        </div>
                        @if ($settings->phone)
                            <div>
                                <dt class="text-zinc-500">{{ __('Phone') }}</dt>
                                <dd class="text-zinc-900 dark:text-white">{{ $settings->phone }}</dd>
                            </div>
                        @endif
                        @if ($settings->email)
                            <div>
                                <dt class="text-zinc-500">{{ __('Email') }}</dt>
                                <dd class="text-zinc-900 dark:text-white">{{ $settings->email }}</dd>
                            </div>
                        @endif
                        @if ($settings->tin_number)
                            <div>
                                <dt class="text-zinc-500">{{ __('TIN') }}</dt>
                                <dd class="text-zinc-900 dark:text-white">{{ $settings->tin_number }}</dd>
                            </div>
                        @endif
                        @if ($settings->address)
                            <div>
                                <dt class="text-zinc-500">{{ __('Address') }}</dt>
                                <dd class="whitespace-pre-wrap text-zinc-900 dark:text-white">{{ $settings->address }}</dd>
                            </div>
                        @endif
                    </dl>
                </flux:card>

                <flux:card>
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            <i class="fa-duotone fa-money-bills" aria-hidden="true"></i>
                        </div>
                        <flux:heading size="lg">{{ __('Payment Summary') }}</flux:heading>
                    </div>

                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-zinc-500">{{ __('Status') }}</dt>
                            @if ($paymentStatus)
                                <flux:badge color="{{ $paymentStatus->color() }}" size="sm">
                                    {{ $paymentStatus->label() }}
                                </flux:badge>
                            @else
                                <span class="text-zinc-500">N/A</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-zinc-500">{{ __('Paid Amount') }}</dt>
                            <dd class="font-mono font-semibold text-green-600 dark:text-green-400">
                                {{ money_tzs($invoice->order?->paid_amount ?? 0) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-zinc-500">{{ __('Balance Due') }}</dt>
                            <dd class="font-mono font-semibold {{ $isOverdue ? 'text-red-600 dark:text-red-400' : '' }}">
                                {{ money_tzs($balanceDue) }}
                            </dd>
                        </div>
                    </dl>
                </flux:card>

                @can('send', $invoice)
                    <flux:card>
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                <i class="fa-duotone fa-paper-plane" aria-hidden="true"></i>
                            </div>
                            <div>
                                <flux:heading size="lg">{{ __('Send Invoice') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Email the current invoice directly to the customer.') }}</flux:text>
                            </div>
                        </div>

                        <flux:input wire:model="emailTo" type="email" label="{{ __('Recipient Email') }}" placeholder="customer@example.com" />

                        @if ($invoice->sent_at)
                            <flux:text class="mt-2 text-xs text-zinc-500">
                                {{ __('Last sent to') }} {{ $invoice->sent_to_email }} {{ __('on') }} {{ $invoice->sent_at->format('M d, Y H:i') }}
                            </flux:text>
                        @endif

                        <flux:button class="mt-4" variant="primary" wire:click="sendByEmail" type="button">
                            <i class="fa-duotone fa-paper-plane mr-1.5" aria-hidden="true"></i>
                            {{ __('Send via Email') }}
                        </flux:button>
                    </flux:card>
                @endcan
            </div>
        </div>
    </flux:main>
</div>

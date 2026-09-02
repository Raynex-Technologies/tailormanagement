<div>
    <flux:main class="p-0">
        <section
            class="mb-6 overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:p-6"
            style="background: linear-gradient(135deg, var(--tm-hero) 0%, color-mix(in srgb, var(--tm-hero) 88%, #ffffff 12%) 100%);"
            data-theme-hero data-invoice-workspace-header
        >
            <flux:breadcrumbs class="mb-5 text-white/70">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" class="!text-white/70 hover:!text-white" wire:navigate />
                <flux:breadcrumbs.item :href="route('invoices.index')" class="!text-white/70 hover:!text-white" wire:navigate>{{ __('Invoices') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item class="!text-white">{{ $invoice->invoice_no }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="xl" class="!text-white">{{ __('Invoice :invoice', ['invoice' => $invoice->invoice_no]) }}</flux:heading>
                        @if ($invoice->sent_at)
                            <span class="rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold text-white">{{ __('Sent') }}</span>
                        @endif
                        @if ($isOverdue)
                            <span class="rounded-full bg-rose-500/90 px-2.5 py-1 text-xs font-semibold text-white">{{ __('Overdue') }}</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-white/70">
                        {{ __('Order :order', ['order' => $invoice->order?->order_no ?? 'N/A']) }}
                        @if ($invoice->order?->customer?->name)
                            <span class="mx-1.5" aria-hidden="true">·</span>
                            {{ $invoice->order->customer->name }}
                        @endif
                    </p>
                </div>

                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end">
                    <flux:button class="w-full !border-white/25 !bg-white/10 !text-white hover:!bg-white/20 sm:w-auto" variant="outline" icon="printer" :href="route('invoices.print', $invoice)" target="_blank">
                        {{ __('Print Invoice') }}
                    </flux:button>
                    <flux:button class="w-full !border-white/25 !bg-white/10 !text-white hover:!bg-white/20 sm:w-auto" variant="outline" icon="arrow-down-tray" :href="route('invoices.download', $invoice)">
                        {{ __('Download') }}
                    </flux:button>
                    @if (! $isEditing)
                        @can('update', $invoice)
                            <flux:button class="w-full sm:w-auto" variant="primary" icon="pencil-square" wire:click="startEditing">
                                {{ __('Edit Invoice') }}
                            </flux:button>
                        @endcan
                    @endif
                </div>
            </div>
        </section>

        @if (session('success'))
            <flux:callout class="mb-6" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if ($errors->any())
            <flux:callout class="mb-6" variant="danger" icon="exclamation-circle">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        <section class="mb-6" aria-labelledby="invoice-financial-summary-heading" data-invoice-financial-summary>
            <div class="mb-3">
                <h2 id="invoice-financial-summary-heading" class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Financial Summary') }}</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Current invoice value and canonical order payment state.') }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <flux:card data-invoice-summary-card="total">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</p>
                            <p class="mt-2 text-2xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ money_tzs($financialSummary['total']) }}</p>
                        </div>
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300">
                            <i class="fa-duotone fa-coins" aria-hidden="true"></i>
                        </span>
                    </div>
                </flux:card>
                <flux:card data-invoice-summary-card="paid">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Paid') }}</p>
                            <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ money_tzs($financialSummary['paid']) }}</p>
                        </div>
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <i class="fa-duotone fa-circle-check" aria-hidden="true"></i>
                        </span>
                    </div>
                </flux:card>
                <flux:card data-invoice-summary-card="balance">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Balance') }}</p>
                            <p class="mt-2 text-2xl font-bold tracking-tight {{ $isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400' }}">{{ money_tzs($financialSummary['balance']) }}</p>
                        </div>
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300">
                            <i class="fa-duotone fa-scale-balanced" aria-hidden="true"></i>
                        </span>
                    </div>
                </flux:card>
                <flux:card data-invoice-summary-card="status">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Payment Status') }}</p>
                            <div class="mt-2">
                                @if ($financialSummary['status'])
                                    <flux:badge color="{{ $financialSummary['status']->color() }}" size="lg">
                                        {{ $financialSummary['status']->label() }}
                                    </flux:badge>
                                @else
                                    <span class="text-sm text-zinc-500">N/A</span>
                                @endif
                            </div>
                        </div>
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-300">
                            <i class="fa-duotone fa-wallet" aria-hidden="true"></i>
                        </span>
                    </div>
                </flux:card>
            </div>
        </section>

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
                        <x-money-input wire:model.blur="discount" min="0" step="1" label="{{ __('Discount') }}" :disabled="!$isEditing" />
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
                            @php($packageLinked = (bool) ($line['is_package_linked'] ?? false))
                            @php($packageContext = $line['package_context'] ?? [])
                            @if ($packageContext['starts_package'] ?? false)
                                @php($invoicePackage = $packageContext['package'])
                                <div class="rounded-xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-800/60 dark:bg-violet-950/20" data-invoice-package-group="{{ $invoicePackage['id'] }}">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="font-semibold text-zinc-950 dark:text-white">{{ $invoicePackage['name'] }}</p>
                                            <p class="mt-0.5 text-xs text-zinc-500">{{ __('Package-linked invoice items are grouped below and remain read-only.') }}</p>
                                        </div>
                                        <div class="sm:text-right">
                                            <p class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Configured package value') }}</p>
                                            <p class="font-mono font-semibold text-violet-700 dark:text-violet-300">{{ money_currency($invoicePackage['configured_total'], config('app.currency', 'TZS')) }}</p>
                                        </div>
                                    </div>
                                </div>
                            @elseif ($packageContext['starts_ordinary'] ?? false)
                                <div class="flex items-center gap-3 pt-2">
                                    <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Additional Items') }}</p>
                                    <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
                                </div>
                            @endif
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50" wire:key="invoice-line-{{ $index }}">
                                <div class="mb-3 flex items-start justify-between">
                                    <div class="flex items-center gap-2"><flux:badge size="sm">{{ __('Item') }} {{ $index + 1 }}</flux:badge>@if($packageLinked)<flux:badge size="sm" color="violet">{{ __('Package item') }}</flux:badge>@endif</div>
                                    @if ($isEditing && count($lines) > 1 && ! $packageLinked)
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
                                            :readonly="$packageLinked"
                                        />
                                    </div>
                                    <flux:input
                                        wire:model.live="lines.{{ $index }}.qty"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        label="{{ __('Qty') }}"
                                        :disabled="!$isEditing"
                                        :readonly="$packageLinked"
                                    />
                                    <x-money-input
                                        wire:model.blur="lines.{{ $index }}.unit_price"
                                        min="0"
                                        step="0.01"
                                        label="{{ __('Unit Price') }}"
                                        :disabled="!$isEditing"
                                        :readonly="$packageLinked"
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
                                        :readonly="$packageLinked"
                                    />
                                </div>
                                @if($packageLinked && $isEditing)<p class="mt-2 text-xs text-amber-600">{{ __('Package composition is read-only here. Use Edit Order to make changes.') }}</p>@endif
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
                        @error('lines')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
                        @error('total')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
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

                        @if ($invoice->sent_at)
                            <flux:text class="text-xs text-zinc-500">
                                {{ __('Last sent to') }} {{ $invoice->sent_to_email }} {{ __('on') }} {{ $invoice->sent_at->format('M d, Y H:i') }}
                            </flux:text>
                        @endif

                        @if (! $emailSendingEnabled)
                            <div class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                {{ __('Customer email sending is disabled in Email Setup.') }}
                            </div>
                        @endif

                        <flux:button class="mt-4" variant="primary" wire:click="openSendInvoiceModal" type="button">
                            <i class="fa-duotone fa-paper-plane mr-1.5" aria-hidden="true"></i>
                            {{ __('Send via Email') }}
                        </flux:button>
                    </flux:card>
                @endcan
            </div>
        </div>

        @can('send', $invoice)
            <flux:modal wire:model="showSendInvoiceModal" class="max-w-lg" data-send-invoice-confirmation>
                <div class="space-y-5">
                    <div>
                        <flux:heading size="lg">{{ __('Confirm Invoice Email') }}</flux:heading>
                        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Review the recipient and attachment before sending.') }}
                        </flux:text>
                    </div>

                    @if (! $emailSendingEnabled)
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            {{ __('Customer email sending is disabled. Enable it in Administration > Email Setup before sending this invoice.') }}
                        </flux:callout>
                    @endif

                    <flux:input wire:model="emailTo" type="email" label="{{ __('To') }}" placeholder="customer@example.com" />

                    <dl class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 text-sm dark:divide-zinc-700 dark:border-zinc-700">
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Invoice') }}</dt>
                            <dd class="font-semibold text-zinc-900 dark:text-white">{{ $invoice->invoice_no }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Attachment') }}</dt>
                            <dd class="break-all text-right font-medium text-zinc-900 dark:text-white">{{ $invoiceAttachmentFilename }}</dd>
                        </div>
                    </dl>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <flux:button type="button" variant="ghost" wire:click="closeSendInvoiceModal">
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button
                            type="button"
                            variant="primary"
                            wire:click="sendByEmail"
                            wire:loading.attr="disabled"
                            wire:target="sendByEmail"
                            :disabled="! $emailSendingEnabled"
                        >
                            <i class="fa-duotone fa-paper-plane mr-1.5" aria-hidden="true"></i>
                            {{ __('Send Invoice') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endcan
    </flux:main>
</div>

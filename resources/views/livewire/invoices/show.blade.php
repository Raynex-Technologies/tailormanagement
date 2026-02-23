<div>
    <flux:main class="p-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('invoices.index')" wire:navigate>{{ __('Invoices') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $invoice->invoice_no }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if ($errors->any())
            <flux:callout class="mt-4" variant="danger" icon="exclamation-circle">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ $invoice->invoice_no }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Order') }}: {{ $invoice->order?->order_no ?? 'N/A' }}
                    @if ($invoice->order?->customer?->name)
                        • {{ $invoice->order->customer->name }}
                    @endif
                </flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" variant="subtle" :href="route('invoices.print', $invoice)" target="_blank">
                    <x-icon name="print" class="mr-1 size-4" />
                    {{ __('Print') }}
                </flux:button>
                <flux:button size="sm" variant="subtle" :href="route('invoices.download', $invoice)">
                    <x-icon name="download" class="mr-1 size-4" />
                    {{ __('Download') }}
                </flux:button>
                @if (! $isEditing)
                    @can('update', $invoice)
                        <flux:button size="sm" variant="primary" wire:click="startEditing">
                            <x-icon name="edit" class="mr-1 size-4" />
                            {{ __('Edit Invoice') }}
                        </flux:button>
                    @endcan
                @endif
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <flux:card>
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
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="lg">{{ __('Invoice Items') }}</flux:heading>
                        @if ($isEditing)
                            <flux:button size="sm" variant="subtle" type="button" wire:click="addLine">
                                <x-icon name="add" class="mr-1 size-4" />
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
                                            <x-icon name="delete" class="size-4 text-red-500" />
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
                                <span class="font-mono">{{ number_format($subtotal, 0) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-500">{{ __('Discount') }}</span>
                                <span class="font-mono text-red-600">-{{ number_format($discount ?? 0, 0) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-zinc-200 pt-2 text-lg font-semibold dark:border-zinc-700">
                                <span>{{ __('Total') }}</span>
                                <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ number_format($total, 0) }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($isEditing)
                        <div class="mt-6 flex justify-end gap-2">
                            <flux:button variant="ghost" wire:click="cancelEditing" type="button">{{ __('Cancel') }}</flux:button>
                            <flux:button variant="primary" wire:click="save" type="button">{{ __('Save Invoice') }}</flux:button>
                        </div>
                    @endif
                </flux:card>
            </div>

            <div class="space-y-6">
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Business Details') }}</flux:heading>

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
                                <dd class="text-zinc-900 dark:text-white whitespace-pre-wrap">{{ $settings->address }}</dd>
                            </div>
                        @endif
                    </dl>
                </flux:card>

                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Payment Summary') }}</flux:heading>
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-zinc-500">{{ __('Status') }}</dt>
                            @if ($invoice->order?->payment_status)
                                <flux:badge color="{{ $invoice->order->payment_status->color() }}" size="sm">
                                    {{ $invoice->order->payment_status->label() }}
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
                            <dd class="font-mono font-semibold">
                                {{ money_tzs($invoice->order?->balance_due ?? 0) }}
                            </dd>
                        </div>
                    </dl>
                </flux:card>

                @can('send', $invoice)
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">{{ __('Send Invoice') }}</flux:heading>
                        <flux:input wire:model="emailTo" type="email" label="{{ __('Recipient Email') }}" placeholder="customer@example.com" />

                        @if ($invoice->sent_at)
                            <flux:text class="mt-2 text-xs text-zinc-500">
                                {{ __('Last sent to') }} {{ $invoice->sent_to_email }} {{ __('on') }} {{ $invoice->sent_at->format('M d, Y H:i') }}
                            </flux:text>
                        @endif

                        <flux:button class="mt-4" variant="primary" wire:click="sendByEmail" type="button">
                            <x-icon name="send" class="mr-1 size-4" />
                            {{ __('Send via Email') }}
                        </flux:button>
                    </flux:card>
                @endcan
            </div>
        </div>
    </flux:main>
</div>

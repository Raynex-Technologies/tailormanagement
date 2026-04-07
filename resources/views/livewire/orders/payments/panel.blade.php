<div class="space-y-6">
    {{-- Payment Summary Card --}}
    <flux:card>
        <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="lg">{{ __('Payments') }}</flux:heading>
            @if ($canRecordPayments && $balanceAmount > 0 && $paymentMethods->isNotEmpty())
                <flux:button size="sm" wire:click="openPaymentModal">
                    <x-icon name="add" class="mr-1 size-4" />
                    {{ __('Record Payment') }}
                </flux:button>
            @endif
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Amount') }}</flux:text>
                <flux:heading size="xl">{{ money_tzs($totalAmount) }}</flux:heading>
            </div>
            <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/30">
                <flux:text class="text-sm text-green-600 dark:text-green-400">{{ __('Amount Paid') }}</flux:text>
                <flux:heading size="xl" class="text-green-700 dark:text-green-300">{{ money_tzs($paidAmount) }}</flux:heading>
            </div>
            <div class="rounded-lg {{ $balanceAmount > 0 ? 'bg-amber-50 dark:bg-amber-900/30' : 'bg-green-50 dark:bg-green-900/30' }} p-4">
                <flux:text class="text-sm {{ $balanceAmount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                    {{ __('Balance Due') }}
                </flux:text>
                <flux:heading size="xl" class="{{ $balanceAmount > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-green-700 dark:text-green-300' }}">
                    {{ money_tzs($balanceAmount) }}
                </flux:heading>
            </div>
        </div>
    </flux:card>

    {{-- Payment History Table --}}
    @if ($payments->isNotEmpty())
        <flux:card>
            <flux:heading size="md" class="mb-4">{{ __('Payment History') }}</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Method') }}</flux:table.column>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                    <flux:table.column>{{ __('Received By') }}</flux:table.column>
                    <flux:table.column>{{ __('Note') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($payments as $payment)
                        <flux:table.row>
                            <flux:table.cell>
                                {{ $payment->paid_at?->format('M d, Y H:i') ?? 'N/A' }}
                            </flux:table.cell>
                            <flux:table.cell class="font-semibold text-green-600 dark:text-green-400">
                                {{ money_tzs($payment->amount) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="space-y-1">
                                    <flux:badge size="sm">
                                        {{ $payment->paymentMethod?->name ?? __('Default') }}
                                    </flux:badge>
                                    @if ($payment->paymentMethod?->account_number || $payment->paymentMethod?->account_holder_name)
                                        <div class="text-xs text-zinc-500">
                                            {{ $payment->paymentMethod?->account_number }}
                                            @if ($payment->paymentMethod?->account_number && $payment->paymentMethod?->account_holder_name)
                                                •
                                            @endif
                                            {{ $payment->paymentMethod?->account_holder_name }}
                                        </div>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">
                                {{ $payment->reference ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $payment->receiver?->name ?? 'Unknown' }}
                            </flux:table.cell>
                            <flux:table.cell class="max-w-xs truncate text-zinc-500">
                                {{ $payment->note ?? '-' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @else
        <flux:card>
            <div class="py-6 text-center">
                <x-icon name="payments" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="md" class="mt-4">{{ __('No payments recorded') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Record the first payment when received.') }}</flux:text>
            </div>
        </flux:card>
    @endif

    {{-- Record Payment Modal --}}
    <flux:modal wire:model="showPaymentModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Record Payment') }}</flux:heading>
                <flux:text class="text-zinc-500">
                    {{ __('Order') }}: #{{ $order->order_no }} • {{ __('Balance') }}: {{ money_tzs($balanceAmount) }}
                </flux:text>
            </div>

            <form wire:submit="recordPayment" class="space-y-4">
                {{-- Amount --}}
                <div>
                    <flux:label for="amount">{{ __('Amount') }} *</flux:label>
                    <flux:input
                        type="number"
                        id="amount"
                        wire:model="amount"
                        step="0.01"
                        min="0.01"
                        :max="$balanceAmount"
                        placeholder="Enter amount"
                    />
                    @error('amount')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Payment Method --}}
                <div>
                    <flux:label for="payment_method_id">{{ __('Payment Method') }} *</flux:label>
                    <flux:select id="payment_method_id" wire:model="payment_method_id">
                        <flux:select.option value="">{{ __('-- Select Payment Method --') }}</flux:select.option>
                        @foreach ($paymentMethods as $pm)
                            <flux:select.option value="{{ $pm->id }}">{{ $pm->display_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('payment_method_id')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Reference --}}
                <div>
                    <flux:label for="reference">{{ __('Reference') }}</flux:label>
                    <flux:input
                        type="text"
                        id="reference"
                        wire:model="reference"
                        placeholder="Transaction ID, receipt number, etc."
                    />
                    @error('reference')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Paid At --}}
                <div>
                    <flux:label for="paidAt">{{ __('Payment Date & Time') }}</flux:label>
                    <flux:input
                        type="datetime-local"
                        id="paidAt"
                        wire:model="paidAt"
                    />
                    @error('paidAt')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Note --}}
                <div>
                    <flux:label for="note">{{ __('Note') }}</flux:label>
                    <flux:textarea
                        id="note"
                        wire:model="note"
                        rows="2"
                        placeholder="Optional notes..."
                    />
                    @error('note')
                        <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <flux:button type="button" variant="ghost" wire:click="$set('showPaymentModal', false)">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        <x-icon name="check" class="mr-1 size-4" />
                        {{ __('Record Payment') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>

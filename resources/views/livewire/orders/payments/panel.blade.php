<div data-payment-recorder>
    @if ($canRecordPayments && $balanceAmount > 0 && $paymentMethods->isNotEmpty())
        <button
            type="button"
            wire:click="openPaymentModal"
            wire:loading.attr="disabled"
            wire:target="openPaymentModal"
            class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-transparent text-navy-800 transition-colors hover:text-navy-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:ring-offset-zinc-800"
            aria-label="{{ __('Record Payment') }}"
            title="{{ __('Record Payment') }}"
            data-record-payment-trigger
        >
            <x-icon name="add" class="size-6" />
            <span class="sr-only">{{ __('Record Payment') }}</span>
        </button>
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
                    <x-money-input
                        id="amount"
                        wire:model.blur="amount"
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

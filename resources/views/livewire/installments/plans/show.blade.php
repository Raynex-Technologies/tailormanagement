<div>
    <flux:main class="space-y-6 p-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
            <flux:breadcrumbs.item :href="route('installments.dashboard')" wire:navigate>{{ __('Installments') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('installments.plans.index')" wire:navigate>{{ __('Plans') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $plan->plan_no }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-2xl border border-zinc-200/50 bg-white p-6 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $plan->plan_no }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plan->customer?->name }} · {{ $plan->package_name }}</p>
                </div>

                @can('recordPayment', $plan)
                    @if($plan->remaining_balance > 0.01 && $plan->status?->value === 'active')
                        <flux:button wire:click="openPaymentModal" variant="primary">
                            {{ __('Record Payment') }}
                        </flux:button>
                    @endif
                @endcan
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Package Price') }}</p>
                <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ money_tzs($plan->package_price) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Paid') }}</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ money_tzs($plan->total_paid) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Remaining Balance') }}</p>
                <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ money_tzs($plan->remaining_balance) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200/50 bg-white p-5 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</p>
                <div class="mt-2 flex items-center gap-2">
                    <flux:badge size="sm" color="{{ $plan->status?->color() ?? 'zinc' }}">
                        {{ $plan->status?->label() ?? $plan->status }}
                    </flux:badge>
                    @if($plan->isOverdue())
                        <flux:badge size="sm" color="red">{{ __('Overdue') }}</flux:badge>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="space-y-6">
                <div class="rounded-2xl border border-zinc-200/50 bg-white p-6 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Plan Details') }}</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Frequency') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-white">{{ $plan->payment_frequency?->label() }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Installments') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-white">{{ number_format($plan->installments_count) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Installment Amount') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-white">{{ money_tzs($plan->installment_amount) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Start Date') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-white">{{ $plan->start_date?->format('M d, Y') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('First Due Date') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-white">{{ $plan->first_due_date?->format('M d, Y') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Maturity Date') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-white">{{ $plan->maturity_date?->format('M d, Y') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Duration') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-white">{{ $plan->durationLabel() }}</dd>
                        </div>
                    </dl>

                    @if($plan->notes)
                        <div class="mt-5 rounded-xl bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ $plan->notes }}
                        </div>
                    @endif
                </div>

                <div class="overflow-hidden rounded-2xl border border-zinc-200/50 bg-white shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                    <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-700/50">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Payment History') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    <th class="px-4 py-3">{{ __('Paid At') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                                    <th class="px-4 py-3">{{ __('Method') }}</th>
                                    <th class="px-4 py-3">{{ __('Reference') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse($plan->payments as $payment)
                                    <tr class="text-sm text-zinc-900 dark:text-zinc-100">
                                        <td class="px-4 py-3">{{ $payment->paid_at?->format('M d, Y H:i') }}</td>
                                        <td class="px-4 py-3 text-right">{{ money_tzs($payment->amount) }}</td>
                                        <td class="px-4 py-3">{{ $payment->paymentMethod?->name ?? __('Default') }}</td>
                                        <td class="px-4 py-3">{{ $payment->reference ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('No payments recorded yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-zinc-200/50 bg-white shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-700/50">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Repayment Schedule') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('No.') }}</th>
                                <th class="px-4 py-3">{{ __('Due Date') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Scheduled') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Paid') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Outstanding') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($plan->schedules as $schedule)
                                <tr class="text-sm text-zinc-900 dark:text-zinc-100">
                                    <td class="px-4 py-3">#{{ $schedule->installment_number }}</td>
                                    <td class="px-4 py-3">{{ $schedule->due_date?->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-right">{{ money_tzs($schedule->scheduled_amount) }}</td>
                                    <td class="px-4 py-3 text-right">{{ money_tzs($schedule->paid_amount) }}</td>
                                    <td class="px-4 py-3 text-right">{{ money_tzs($schedule->outstanding_amount) }}</td>
                                    <td class="px-4 py-3">
                                        <flux:badge size="sm" color="{{ $schedule->status?->color() ?? 'zinc' }}">
                                            {{ $schedule->status?->label() ?? $schedule->status }}
                                        </flux:badge>
                                        @if($schedule->isOverdue())
                                            <flux:badge size="sm" color="red" class="ml-1">{{ __('Overdue') }}</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </flux:main>

    <flux:modal wire:model="showPaymentModal" class="max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('Record Installment Payment') }}</flux:heading>

            <form wire:submit="savePayment" class="space-y-4">
                <div>
                    <flux:input wire:model="paymentAmount" type="number" step="0.01" label="{{ __('Amount') }}" />
                    @error('paymentAmount') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <flux:select wire:model="paymentMethodId" label="{{ __('Payment Method') }}">
                            <option value="">{{ __('Select payment method') }}</option>
                            @foreach($this->paymentMethods as $method)
                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                            @endforeach
                        </flux:select>
                        @error('paymentMethodId') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="paymentPaidAt" type="datetime-local" label="{{ __('Paid At') }}" />
                        @error('paymentPaidAt') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <flux:input wire:model="paymentReference" label="{{ __('Reference') }}" />
                    @error('paymentReference') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <flux:textarea wire:model="paymentNote" label="{{ __('Note') }}" rows="3" />
                    @error('paymentNote') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="closePaymentModal">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save Payment') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>

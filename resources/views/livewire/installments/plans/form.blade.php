<flux:main class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
        <flux:breadcrumbs.item :href="route('installments.dashboard')" wire:navigate>{{ __('Installments') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('installments.plans.index')" wire:navigate>{{ __('Plans') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Create Plan') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="grid gap-6 xl:grid-cols-[1fr_0.95fr]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-zinc-200/50 bg-white p-6 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Create Installment Plan') }}</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Assign a customer to a package and generate the repayment schedule automatically.') }}</p>
            </div>

            <form wire:submit="save" class="space-y-6">
                <div class="rounded-2xl border border-zinc-200/50 bg-white p-6 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                    <div class="grid gap-4 md:grid-cols-2">
                        @if(auth()->user()->isGlobalAdmin())
                            <div class="md:col-span-2">
                                <flux:select wire:model.live="branch_id" label="{{ __('Branch') }}">
                                    <option value="">{{ __('Select branch') }}</option>
                                    @foreach($this->branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </flux:select>
                                @error('branch_id') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div>
                            <flux:select wire:model.live="customer_id" label="{{ __('Customer') }}">
                                <option value="">{{ __('Select customer') }}</option>
                                @foreach($this->customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }} - {{ $customer->phone }}</option>
                                @endforeach
                            </flux:select>
                            @error('customer_id') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <flux:select wire:model.live="package_id" label="{{ __('Package') }}">
                                <option value="">{{ __('Select package') }}</option>
                                @foreach($this->packages as $package)
                                    <option value="{{ $package->id }}">{{ $package->name }} - {{ money_tzs($package->price) }}</option>
                                @endforeach
                            </flux:select>
                            @error('package_id') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <flux:input wire:model.live="package_price" type="number" step="0.01" label="{{ __('Package Price') }}" />
                            @error('package_price') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <flux:input wire:model.live="installments_count" type="number" min="1" max="60" label="{{ __('Number of Installments') }}" />
                            @error('installments_count') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <flux:select wire:model.live="payment_frequency" label="{{ __('Payment Frequency') }}">
                                <option value="weekly">{{ __('Weekly') }}</option>
                                <option value="biweekly">{{ __('Biweekly') }}</option>
                                <option value="monthly">{{ __('Monthly') }}</option>
                            </flux:select>
                            @error('payment_frequency') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <flux:input wire:model.live="start_date" type="date" label="{{ __('Plan Start Date') }}" />
                            @error('start_date') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <flux:input wire:model.live="first_due_date" type="date" label="{{ __('First Due Date') }}" />
                            @error('first_due_date') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <flux:textarea wire:model="notes" label="{{ __('Notes') }}" rows="3" />
                            @error('notes') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button :href="route('installments.plans.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Create Plan') }}</flux:button>
                </div>
            </form>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-zinc-200/50 bg-white p-6 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Calculator Preview') }}</h2>
                @if($this->selectedPackage)
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900/40">
                            <p class="text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Selected Package') }}</p>
                            <p class="mt-2 font-semibold text-zinc-900 dark:text-white">{{ $this->selectedPackage->name }}</p>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $this->selectedPackage->durationLabel() }}</p>
                        </div>
                        <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900/40">
                            <p class="text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Calculated Installment') }}</p>
                            <p class="mt-2 font-semibold text-zinc-900 dark:text-white">
                                {{ money_tzs($this->installmentPreview !== [] ? $this->installmentPreview[0]['scheduled_amount'] : 0) }}
                            </p>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Per installment before partial-payment adjustments') }}</p>
                        </div>
                    </div>
                @else
                    <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Choose a package to preview the installment schedule.') }}</p>
                @endif
            </div>

            <div class="overflow-hidden rounded-2xl border border-zinc-200/50 bg-white shadow-sm dark:border-zinc-700/50 dark:bg-zinc-800/50">
                <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-700/50">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Generated Schedule') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('Installment') }}</th>
                                <th class="px-4 py-3">{{ __('Due Date') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($this->installmentPreview as $schedule)
                                <tr class="text-sm text-zinc-900 dark:text-zinc-100">
                                    <td class="px-4 py-3">#{{ $schedule['installment_number'] }}</td>
                                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($schedule['due_date'])->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-right">{{ money_tzs($schedule['scheduled_amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('Enter package and installment details to preview the schedule.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</flux:main>

<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('capital.index') }}" wire:navigate>{{ __('Capital') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $allocation->allocation_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
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

    {{-- Header --}}
    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ $allocation->allocation_no }}</flux:heading>
                    <flux:badge color="{{ $allocation->status->color() }}" size="lg">
                        {{ $allocation->status->label() }}
                    </flux:badge>
                </div>
                <flux:text class="mt-1">
                    {{ __('Accountant') }}: <span class="font-medium">{{ $allocation->accountant?->name ?? 'N/A' }}</span>
                </flux:text>
            </div>

            @can('close', $allocation)
                @if ($allocation->status === \App\Enums\CapitalAllocationStatus::Open)
                    <flux:button variant="subtle" wire:click="openCloseModal">
                        <x-icon name="lock" class="mr-1 size-4" />
                        {{ __('Close Allocation') }}
                    </flux:button>
                @endif
            @endcan
        </div>
    </flux:card>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Initial Amount') }}</flux:text>
            <flux:heading size="lg" class="mt-1 font-mono text-indigo-600 dark:text-indigo-400">
                {{ money_tzs($allocation->initial_amount) }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Spent Amount') }}</flux:text>
            <flux:heading size="lg" class="mt-1 font-mono text-red-600 dark:text-red-400">
                {{ money_tzs($allocation->spent_amount) }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Available Balance') }}</flux:text>
            <flux:heading size="lg" class="mt-1 font-mono text-green-600 dark:text-green-400">
                {{ money_tzs($availableBalance) }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Period') }}</flux:text>
            <flux:heading size="md" class="mt-1">
                {{ $allocation->starts_on?->format('M d, Y') ?? 'N/A' }}
                <span class="text-zinc-400">-</span>
                {{ $allocation->ends_on?->format('M d, Y') ?? __('Ongoing') }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Details --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            {{-- Transactions --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Transactions') }}</flux:heading>

                @if ($allocation->transactions->isEmpty())
                    <div class="py-8 text-center">
                        <x-icon name="description" class="mx-auto size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text class="mt-2 text-zinc-500">{{ __('No transactions yet.') }}</flux:text>
                    </div>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Date') }}</flux:table.column>
                            <flux:table.column>{{ __('Type') }}</flux:table.column>
                            <flux:table.column>{{ __('Amount') }}</flux:table.column>
                            <flux:table.column>{{ __('Reference') }}</flux:table.column>
                            <flux:table.column>{{ __('By') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($allocation->transactions->sortByDesc('created_at') as $transaction)
                                <flux:table.row>
                                    <flux:table.cell class="text-sm">
                                        {{ $transaction->created_at->format('M d, Y H:i') }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge color="{{ $transaction->type->color() }}" size="sm">
                                            {{ $transaction->type->label() }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="font-mono {{ $transaction->type === \App\Enums\CapitalTransactionType::Debit ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                        {{ $transaction->type === \App\Enums\CapitalTransactionType::Debit ? '-' : '+' }}{{ money_tzs($transaction->amount) }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-sm text-zinc-500">
                                        @if ($transaction->reference)
                                            @if ($transaction->reference instanceof \App\Models\PurchaseRequest)
                                                <a href="{{ route('procurement.requests.show', $transaction->reference) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                                                    PR #{{ $transaction->reference->request_no }}
                                                </a>
                                            @else
                                                {{ class_basename($transaction->reference_type) }} #{{ $transaction->reference_id }}
                                            @endif
                                        @else
                                            {{ $transaction->note ?? '-' }}
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-sm">
                                        {{ $transaction->creator?->name ?? 'System' }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>

        {{-- Sidebar Info --}}
        <div class="space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Details') }}</flux:heading>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Created At') }}</dt>
                        <dd>{{ $allocation->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Created By') }}</dt>
                        <dd>{{ $allocation->creator?->name ?? 'N/A' }}</dd>
                    </div>
                    @if ($allocation->closed_at)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Closed At') }}</dt>
                            <dd>{{ $allocation->closed_at->format('M d, Y H:i') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Closing Balance') }}</dt>
                            <dd class="font-mono">{{ money_tzs($allocation->closing_balance) }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($allocation->note)
                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:label>{{ __('Note') }}</flux:label>
                        <flux:text class="mt-1 whitespace-pre-wrap">{{ $allocation->note }}</flux:text>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>

    {{-- Close Modal --}}
    <flux:modal wire:model="showCloseModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Close Allocation') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('This will close the allocation and record the final balance. This action cannot be undone.') }}
            </flux:text>

            <div>
                <flux:label>{{ __('Current Balance') }}</flux:label>
                <flux:heading size="lg" class="font-mono text-green-600 dark:text-green-400">
                    {{ money_tzs($availableBalance) }}
                </flux:heading>
            </div>

            <div>
                <flux:label for="closeNote">{{ __('Note (optional)') }}</flux:label>
                <flux:textarea id="closeNote" wire:model="closeNote" rows="2" placeholder="Reason for closing..." />
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showCloseModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="button" variant="danger" wire:click="closeAllocation">
                    <x-icon name="lock" class="mr-1 size-4" />
                    {{ __('Close Allocation') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</flux:main>

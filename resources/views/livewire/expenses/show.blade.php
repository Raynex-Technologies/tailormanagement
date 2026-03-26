<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('expenses.index') }}" wire:navigate>{{ __('Expenses') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $expense->expense_date->format('M d, Y') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Header --}}
    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ __('Expense Details') }}</flux:heading>
                    @if ($expense->capitalAllocation)
                        <flux:badge color="green" size="lg">{{ __('Capital Linked') }}</flux:badge>
                    @endif
                </div>
                <flux:text class="mt-1">
                    {{ $expense->expense_date->format('F d, Y') }}
                    <span class="text-zinc-400">â€¢</span>
                    {{ $expense->category?->name ?? __('Uncategorized') }}
                </flux:text>
            </div>

            @if ($this->canEdit)
                <flux:button size="sm" variant="subtle" :href="route('expenses.edit', $expense)" wire:navigate>
                    <x-icon name="edit" class="mr-1 size-4" />
                    {{ __('Edit') }}
                </flux:button>
            @endif
        </div>
    </flux:card>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Amount') }}</flux:text>
            <flux:heading size="xl" class="mt-1 font-mono text-red-600 dark:text-red-400">
                {{ money_tzs($expense->amount) }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Vendor') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ $expense->vendor ?? '-' }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Category') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ $expense->category?->name ?? __('Uncategorized') }}
            </flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">{{ __('Reference') }}</flux:text>
            <flux:heading size="lg" class="mt-1">
                {{ $expense->reference ?? '-' }}
            </flux:heading>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main Details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Note --}}
            @if ($expense->note)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Note') }}</flux:heading>
                    <flux:text class="whitespace-pre-wrap">{{ $expense->note }}</flux:text>
                </flux:card>
            @endif

            {{-- Capital Transaction --}}
            @if ($expense->capitalAllocation && $capitalTransaction)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Capital Transaction') }}</flux:heading>

                    <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/30">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Allocation') }}</dt>
                                <dd>
                                    <a href="{{ route('capital.show', $expense->capitalAllocation) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" wire:navigate>
                                        {{ $expense->capitalAllocation->allocation_no }}
                                    </a>
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Transaction Type') }}</dt>
                                <dd>
                                    <flux:badge color="{{ $capitalTransaction->type->color() }}" size="sm">
                                        {{ $capitalTransaction->type->label() }}
                                    </flux:badge>
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Amount Deducted') }}</dt>
                                <dd class="font-mono font-medium text-red-600 dark:text-red-400">
                                    {{ money_tzs($capitalTransaction->amount) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Transaction Date') }}</dt>
                                <dd>{{ $capitalTransaction->created_at->format('M d, Y H:i') }}</dd>
                            </div>
                            @if ($capitalTransaction->note)
                                <div class="flex justify-between">
                                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Note') }}</dt>
                                    <dd>{{ $capitalTransaction->note }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <flux:text class="mt-4 text-sm text-zinc-500">
                        <x-icon name="info" class="mr-1 inline size-4" />
                        {{ __('This expense is linked to a capital allocation. The amount and allocation cannot be changed.') }}
                    </flux:text>
                </flux:card>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Meta Info --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Details') }}</flux:heading>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Date') }}</dt>
                        <dd>{{ $expense->expense_date->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Created At') }}</dt>
                        <dd>{{ $expense->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Created By') }}</dt>
                        <dd>{{ $expense->creator?->name ?? 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Last Updated') }}</dt>
                        <dd>{{ $expense->updated_at->format('M d, Y H:i') }}</dd>
                    </div>
                </dl>
            </flux:card>

            {{-- Capital Allocation Link --}}
            @if ($expense->capitalAllocation)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Capital Allocation') }}</flux:heading>

                    <a href="{{ route('capital.show', $expense->capitalAllocation) }}" class="block rounded-lg border border-green-200 bg-green-50 p-4 hover:bg-green-100 dark:border-green-800 dark:bg-green-900/30 dark:hover:bg-green-900/50" wire:navigate>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium">{{ $expense->capitalAllocation->allocation_no }}</span>
                                <span class="block text-sm text-zinc-500">{{ $expense->capitalAllocation->accountant?->name ?? 'N/A' }}</span>
                            </div>
                            <flux:badge color="{{ $expense->capitalAllocation->status->color() }}" size="sm">
                                {{ $expense->capitalAllocation->status->label() }}
                            </flux:badge>
                        </div>
                    </a>
                </flux:card>
            @endif
        </div>
    </div>
</flux:main>

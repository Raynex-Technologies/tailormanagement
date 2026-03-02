<flux:main class="space-y-6 p-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Capital Allocations') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Header & Stats --}}
    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Capital Allocations') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Manage budget allocations for accountants.') }}</flux:text>
            </div>
            @can('capital.assign')
                <flux:button variant="primary" :href="route('capital.create')" wire:navigate>
                    <x-icon name="add" class="mr-1 size-4" />
                    {{ __('New Allocation') }}
                </flux:button>
            @endcan
        </div>

        {{-- Stats --}}
        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                <flux:text class="text-sm text-zinc-500">{{ __('Total') }}</flux:text>
                <flux:heading size="xl">{{ $stats['total'] }}</flux:heading>
            </div>
            <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/30">
                <flux:text class="text-sm text-green-600 dark:text-green-400">{{ __('Open') }}</flux:text>
                <flux:heading size="xl" class="text-green-700 dark:text-green-300">{{ $stats['open'] }}</flux:heading>
            </div>
            <div class="rounded-lg bg-indigo-50 p-4 dark:bg-indigo-900/30">
                <flux:text class="text-sm text-indigo-600 dark:text-indigo-400">{{ __('Total Allocated') }}</flux:text>
                <flux:heading size="lg" class="text-indigo-700 dark:text-indigo-300">{{ money_tzs($stats['total_allocated']) }}</flux:heading>
            </div>
            <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-900/30">
                <flux:text class="text-sm text-amber-600 dark:text-amber-400">{{ __('Total Spent') }}</flux:text>
                <flux:heading size="lg" class="text-amber-700 dark:text-amber-300">{{ money_tzs($stats['total_spent']) }}</flux:heading>
            </div>
        </div>
    </flux:card>

    {{-- Filters --}}
    <flux:card>
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="w-full md:w-1/3">
                <flux:input wire:model.blur="search" placeholder="Search allocations..." icon="magnifying-glass" />
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" :variant="$statusFilter === null ? 'primary' : 'ghost'" wire:click="setStatusFilter(null)">
                    {{ __('All') }}
                </flux:button>
                @foreach ($statuses as $status)
                    <flux:button size="sm" :variant="$statusFilter === $status->value ? 'primary' : 'ghost'" wire:click="setStatusFilter('{{ $status->value }}')">
                        {{ $status->label() }}
                    </flux:button>
                @endforeach
            </div>
        </div>
    </flux:card>

    {{-- Allocations Table --}}
    <flux:card>
        @if ($allocations->isEmpty())
            <div class="py-12 text-center">
                <x-icon name="payments" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-4">{{ __('No allocations found') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Create a new capital allocation to get started.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Allocation No') }}</flux:table.column>
                    <flux:table.column>{{ __('Accountant') }}</flux:table.column>
                    <flux:table.column>{{ __('Period') }}</flux:table.column>
                    <flux:table.column>{{ __('Initial') }}</flux:table.column>
                    <flux:table.column>{{ __('Spent') }}</flux:table.column>
                    <flux:table.column>{{ __('Balance') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($allocations as $allocation)
                        <flux:table.row wire:key="allocation-{{ $allocation->id }}">
                            <flux:table.cell class="font-medium">
                                {{ $allocation->allocation_no }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $allocation->accountant?->name ?? 'N/A' }}
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">
                                {{ $allocation->starts_on?->format('M d, Y') ?? 'N/A' }}
                                @if ($allocation->ends_on)
                                    - {{ $allocation->ends_on->format('M d, Y') }}
                                @else
                                    - {{ __('Ongoing') }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="font-mono">
                                {{ money_tzs($allocation->initial_amount) }}
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-red-600 dark:text-red-400">
                                {{ money_tzs($allocation->spent_amount) }}
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-green-600 dark:text-green-400">
                                {{ money_tzs($allocation->remaining_balance) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="{{ $allocation->status->color() }}" size="sm">
                                    {{ $allocation->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="xs" variant="ghost" :href="route('capital.show', $allocation)" wire:navigate>
                                    <x-icon name="visibility" class="size-4" />
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $allocations->links() }}
            </div>
        @endif
    </flux:card>
</flux:main>

<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Purchase Requests') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Flash Messages --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Header --}}
    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Purchase Requests') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Manage purchase requests for inventory items.') }}</flux:text>
            </div>
            @can('procurement.request.create')
                <flux:button variant="primary" :href="route('procurement.requests.create')" wire:navigate>
                    <x-icon name="add" class="mr-1 size-4" />
                    {{ __('New Request') }}
                </flux:button>
            @endcan
        </div>
    </flux:card>

    {{-- Filters --}}
    <flux:card>
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="w-full md:w-1/3">
                <flux:input wire:model.blur="search" placeholder="Search requests..." icon="magnifying-glass" />
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" :variant="$statusFilter === null ? 'primary' : 'ghost'" wire:click="setStatusFilter(null)">
                    {{ __('All') }}
                </flux:button>
                @foreach ($statuses as $status)
                    <flux:button size="sm" :variant="$statusFilter === $status->value ? 'primary' : 'ghost'" wire:click="setStatusFilter('{{ $status->value }}')">
                        {{ $status->label() }}
                        @if (isset($statusCounts[$status->value]))
                            <span class="ml-1 text-xs opacity-75">({{ $statusCounts[$status->value] }})</span>
                        @endif
                    </flux:button>
                @endforeach
            </div>
        </div>
    </flux:card>

    {{-- Requests Table --}}
    <flux:card>
        @if ($requests->isEmpty())
            <div class="py-12 text-center">
                <x-icon name="assignment" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-4">{{ __('No purchase requests found') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Create a new purchase request to get started.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Request No') }}</flux:table.column>
                    <flux:table.column>{{ __('Requested By') }}</flux:table.column>
                    <flux:table.column>{{ __('Items') }}</flux:table.column>
                    <flux:table.column>{{ __('Est. Total') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Created') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($requests as $request)
                        <flux:table.row wire:key="pr-{{ $request->id }}">
                            <flux:table.cell class="font-medium">
                                {{ $request->request_no }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $request->requester?->name ?? 'N/A' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $request->items_count ?? $request->items()->count() }} {{ __('items') }}
                            </flux:table.cell>
                            <flux:table.cell class="font-mono">
                                {{ money_tzs($request->estimated_total) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="{{ $request->status->color() }}" size="sm">
                                    {{ $request->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">
                                {{ $request->created_at->format('M d, Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="xs" variant="ghost" :href="route('procurement.requests.show', $request)" wire:navigate>
                                    <x-icon name="visibility" class="size-4" />
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $requests->links() }}
            </div>
        @endif
    </flux:card>
</flux:main>

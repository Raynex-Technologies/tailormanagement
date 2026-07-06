<flux:main class="space-y-6">
    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('SMS Logs') }}</flux:breadcrumbs.item>
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

    {{-- Header & Stats --}}
    <flux:card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('SMS Logs') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('View all SMS messages sent from the system.') }}</flux:text>
            </div>
            @can('sms.send')
                <flux:button type="button" variant="primary" icon="arrow-path" wire:click="openRetryModal">
                    {{ __('Retry Failed') }}
                </flux:button>
            @endcan
        </div>

        {{-- Stats Cards --}}
        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-6">
            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                <flux:text class="text-sm text-zinc-500">{{ __('Total') }}</flux:text>
                <flux:heading size="xl">{{ number_format($stats['total']) }}</flux:heading>
            </div>
            <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/30">
                <flux:text class="text-sm text-green-600 dark:text-green-400">{{ __('Sent') }}</flux:text>
                <flux:heading size="xl" class="text-green-700 dark:text-green-300">{{ number_format($stats['sent']) }}</flux:heading>
            </div>
            <div class="rounded-lg bg-red-50 p-4 dark:bg-red-900/30">
                <flux:text class="text-sm text-red-600 dark:text-red-400">{{ __('Failed') }}</flux:text>
                <flux:heading size="xl" class="text-red-700 dark:text-red-300">{{ number_format($stats['failed']) }}</flux:heading>
            </div>
            <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/30">
                <flux:text class="text-sm text-blue-600 dark:text-blue-400">{{ __('Resolved') }}</flux:text>
                <flux:heading size="xl" class="text-blue-700 dark:text-blue-300">{{ number_format($stats['resolved']) }}</flux:heading>
            </div>
            <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-900/30">
                <flux:text class="text-sm text-amber-600 dark:text-amber-400">{{ __('Queued') }}</flux:text>
                <flux:heading size="xl" class="text-amber-700 dark:text-amber-300">{{ number_format($stats['queued']) }}</flux:heading>
            </div>
            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                <flux:text class="text-sm text-zinc-500">{{ __('Skipped') }}</flux:text>
                <flux:heading size="xl">{{ number_format($stats['skipped']) }}</flux:heading>
            </div>
        </div>
    </flux:card>

    {{-- Filters --}}
    <flux:card>
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            {{-- Search --}}
            <div class="w-full md:w-1/3">
                <flux:label for="search">{{ __('Search') }}</flux:label>
                <flux:input
                    type="text"
                    id="search"
                    wire:model.blur="search"
                    placeholder="Phone, message, order no..."
                />
            </div>

            {{-- Status Filter --}}
            <div class="flex flex-wrap items-center gap-2">
                <flux:button
                    size="sm"
                    :variant="$statusFilter === null ? 'primary' : 'ghost'"
                    wire:click="setStatusFilter(null)"
                >
                    {{ __('All') }}
                </flux:button>
                @foreach ($smsStatuses as $status)
                    <flux:button
                        size="sm"
                        :variant="$statusFilter === $status->value ? 'primary' : 'ghost'"
                        wire:click="setStatusFilter('{{ $status->value }}')"
                    >
                        {{ $status->label() }}
                    </flux:button>
                @endforeach
            </div>
        </div>

        {{-- Date Range --}}
        <div class="mt-4 flex flex-col gap-4 md:flex-row md:items-end">
            <div class="w-full md:w-1/4">
                <flux:label for="dateFrom">{{ __('From Date') }}</flux:label>
                <flux:input type="date" id="dateFrom" wire:model.blur="dateFrom" />
            </div>
            <div class="w-full md:w-1/4">
                <flux:label for="dateTo">{{ __('To Date') }}</flux:label>
                <flux:input type="date" id="dateTo" wire:model.blur="dateTo" />
            </div>
            <div class="w-full md:w-auto">
                <flux:checkbox
                    wire:model.live="includeResolvedFailures"
                    label="{{ __('Include resolved failed attempts') }}"
                />
            </div>
            @if ($search || $statusFilter || $dateFrom || $dateTo || $includeResolvedFailures)
                <flux:button size="sm" variant="ghost" wire:click="clearFilters">
                    <x-icon name="close" class="mr-1 size-4" />
                    {{ __('Clear Filters') }}
                </flux:button>
            @endif
        </div>
    </flux:card>

    {{-- SMS Logs Table --}}
    <flux:card>
        @if ($logs->isEmpty())
            <div class="py-12 text-center">
                <x-icon name="chat" class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-4">{{ __('No SMS logs found') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('SMS logs will appear here when messages are sent.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('To') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                    <flux:table.column>{{ __('Message') }}</flux:table.column>
                    <flux:table.column>{{ __('Message ID') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($logs as $log)
                        <flux:table.row wire:key="sms-{{ $log->id }}">
                            <flux:table.cell class="whitespace-nowrap">
                                {{ $log->created_at->format('M d, Y H:i') }}
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-sm">
                                {{ phone_display($log->to) ?? $log->to }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="{{ $log->status->color() }}" size="sm">
                                    {{ $log->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($log->reference)
                                    @if ($log->reference instanceof \App\Models\Order)
                                        <a
                                            href="{{ route('orders.show', $log->reference) }}"
                                            class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                            wire:navigate
                                        >
                                            Order #{{ $log->reference->order_no }}
                                        </a>
                                    @else
                                        {{ class_basename($log->reference_type) }} #{{ $log->reference_id }}
                                    @endif
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-w-xs truncate text-sm text-zinc-600 dark:text-zinc-400">
                                {{ \Illuminate\Support\Str::limit($log->message, 50) }}
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-xs text-zinc-500">
                                {{ $log->provider_message_id ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="xs" variant="ghost" wire:click="showDetails({{ $log->id }})">
                                    <x-icon name="visibility" class="size-4" />
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Detail Modal --}}
    <flux:modal wire:model="showDetailModal" class="w-full max-w-2xl">
        @if ($selectedLog)
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('SMS Details') }}</flux:heading>
                    <flux:badge color="{{ $selectedLog->status->color() }}" size="lg">
                        {{ $selectedLog->status->label() }}
                    </flux:badge>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:label>{{ __('Sent At') }}</flux:label>
                        <flux:text>{{ $selectedLog->created_at->format('M d, Y H:i:s') }}</flux:text>
                    </div>
                    <div>
                        <flux:label>{{ __('Recipient') }}</flux:label>
                        <flux:text class="font-mono">{{ phone_display($selectedLog->to) ?? $selectedLog->to }}</flux:text>
                    </div>
                    <div>
                        <flux:label>{{ __('Provider') }}</flux:label>
                        <flux:text>{{ ucfirst($selectedLog->provider) }}</flux:text>
                    </div>
                    @if ($selectedLog->template_code)
                        <div>
                            <flux:label>{{ __('Template') }}</flux:label>
                            <flux:text class="font-mono text-sm">{{ $selectedLog->template_code }}</flux:text>
                        </div>
                    @endif
                    @if ($selectedLog->skip_reason)
                        <div>
                            <flux:label>{{ __('Skip Reason') }}</flux:label>
                            <flux:text class="font-mono text-sm">{{ $selectedLog->skip_reason }}</flux:text>
                        </div>
                    @endif
                    <div>
                        <flux:label>{{ __('Message ID') }}</flux:label>
                        <flux:text class="font-mono text-sm">{{ $selectedLog->provider_message_id ?? 'N/A' }}</flux:text>
                    </div>
                    @if ($selectedLog->reference)
                        <div>
                            <flux:label>{{ __('Reference') }}</flux:label>
                            <flux:text>
                                @if ($selectedLog->reference instanceof \App\Models\Order)
                                    <a
                                        href="{{ route('orders.show', $selectedLog->reference) }}"
                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                        wire:navigate
                                    >
                                        Order #{{ $selectedLog->reference->order_no }}
                                    </a>
                                @else
                                    {{ class_basename($selectedLog->reference_type) }} #{{ $selectedLog->reference_id }}
                                @endif
                            </flux:text>
                        </div>
                    @endif
                    @if ($selectedLog->creator)
                        <div>
                            <flux:label>{{ __('Initiated By') }}</flux:label>
                            <flux:text>{{ $selectedLog->creator->name }}</flux:text>
                        </div>
                    @endif
                </div>

                <div>
                    <flux:label>{{ __('Message') }}</flux:label>
                    <div class="mt-1 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                        <flux:text class="whitespace-pre-wrap">{{ $selectedLog->message }}</flux:text>
                    </div>
                </div>

                @if ($selectedLog->provider_response)
                    <div>
                        <flux:label>{{ __('Provider Response') }}</flux:label>
                        <div class="mt-1 overflow-x-auto rounded-lg bg-zinc-900 p-4 text-sm text-zinc-300">
                            <pre>{{ json_encode(json_decode($selectedLog->provider_response), JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end pt-4">
                    <flux:button variant="ghost" wire:click="closeDetails">
                        {{ __('Close') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="showRetryModal" class="w-full max-w-lg">
        <form wire:submit="retryFailedMessages" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Retry Failed Messages') }}</flux:heading>
                <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Select a date range. Failed messages in that range will be sent again as new attempts.') }}
                </flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:input type="date" wire:model.blur="retryDateFrom" label="{{ __('From Date') }}" required />
                    @error('retryDateFrom')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <flux:input type="date" wire:model.blur="retryDateTo" label="{{ __('To Date') }}" required />
                    @error('retryDateTo')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ __('Original failed logs will remain unchanged. Successful retry attempts hide the original failures from unresolved failed logs by default.') }}
            </flux:callout>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showRetryModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" icon="arrow-path" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="retryFailedMessages">{{ __('Retry Messages') }}</span>
                    <span wire:loading wire:target="retryFailedMessages">{{ __('Retrying...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</flux:main>

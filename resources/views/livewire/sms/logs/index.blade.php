<flux:main class="space-y-6 p-0" x-data="{ filtersOpen: @js((bool) ($search || $statusFilter || $dateFrom || $dateTo || $includeResolvedFailures)) }">
    <section class="overflow-hidden rounded-2xl p-5 shadow-lg sm:p-6" style="background: linear-gradient(135deg, var(--tm-hero) 0%, color-mix(in srgb, var(--tm-hero) 88%, var(--tm-hero-foreground) 12%) 100%);" data-theme-hero data-sms-workspace-header>
        <flux:breadcrumbs class="mb-5">
            <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
            <flux:breadcrumbs.item>{{ __('SMS Logs') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('SMS Logs') }}</flux:heading>
                <flux:text class="mt-1 block text-sm">{{ __('View all SMS messages sent from the system.') }}</flux:text>
            </div>
            @can('sms.send')
                @if ($hasLogsToClear)
                <div class="flex flex-wrap items-center gap-2">
                    @if ($stats['failed'] > 0)
                        <flux:button type="button" variant="primary" icon="arrow-path" wire:click="openRetryModal">
                            {{ __('Retry Failed') }}
                        </flux:button>
                    @endif
                    <flux:button type="button" variant="danger" icon="trash" wire:click="openClearLogsModal">
                        {{ __('Clear Logs') }}
                    </flux:button>
                </div>
                @endif
            @endcan
        </div>
    </section>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-circle">{{ session('error') }}</flux:callout>
    @endif

    <section aria-labelledby="sms-overview-heading">
        <div class="mb-3">
            <h2 id="sms-overview-heading" class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('SMS overview') }}</h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('All dates in your current branch scope') }}</p>
        </div>
        @php
            $smsMetrics = [
                ['key' => 'total', 'label' => __('Total'), 'icon' => 'chat-bubble-left-right', 'color' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'],
                ['key' => 'sent', 'label' => __('Sent'), 'icon' => 'check-circle', 'color' => 'bg-green-50 text-green-600 dark:bg-green-950/50 dark:text-green-300'],
                ['key' => 'failed', 'label' => __('Failed'), 'icon' => 'exclamation-circle', 'color' => 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300'],
                ['key' => 'queued', 'label' => __('Queued'), 'icon' => 'clock', 'color' => 'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300'],
            ];
        @endphp
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            @foreach ($smsMetrics as $metric)
                <flux:card class="relative overflow-hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $metric['label'] }}</p>
                            <p class="mt-2 break-words text-2xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ number_format($stats[$metric['key']]) }}</p>
                        </div>
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl {{ $metric['color'] }}">
                            <flux:icon :name="$metric['icon']" class="size-5" />
                        </span>
                    </div>
                </flux:card>
            @endforeach
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="lg">{{ __('Message history') }}</flux:heading>
        <flux:button type="button" variant="ghost" icon="funnel" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="sms-filters">
            {{ __('Filters') }}
            @if ($search || $statusFilter || $dateFrom || $dateTo || $includeResolvedFailures)
                <span class="size-2 rounded-full" style="background: var(--tm-accent);" aria-label="{{ __('Filters active') }}"></span>
            @endif
        </flux:button>
    </div>

    <flux:card id="sms-filters" x-show="filtersOpen" x-collapse x-cloak wire:key="sms-filter-panel">
        <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Phone, message, order no...')" icon="magnifying-glass" />
            <flux:select wire:model.live="statusFilter" :label="__('Status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($smsStatuses as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model.live="dateFrom" :label="__('From Date')" />
            <flux:input type="date" wire:model.live="dateTo" :label="__('To Date')" />
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:checkbox wire:model.live="includeResolvedFailures" :label="__('Include resolved failed attempts')" />
            <flux:button type="button" size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('Clear Filters') }}</flux:button>
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
                                            class="font-medium text-[var(--tailorpro-breadcrumb-accent)] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--tm-accent)] dark:text-[var(--tm-accent)]"
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
                                <flux:button size="xs" variant="ghost" wire:click="showDetails({{ $log->id }})" :aria-label="__('View SMS details')">
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
                                        class="font-medium text-[var(--tailorpro-breadcrumb-accent)] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--tm-accent)] dark:text-[var(--tm-accent)]"
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

    <flux:modal wire:model="showClearLogsModal" class="w-full max-w-lg">
        <form wire:submit="clearLogs" class="space-y-5">
            <flux:heading size="lg">{{ __('Clear Logs') }}</flux:heading>
            <flux:text>{{ __('Soft delete all SMS logs in your current branch scope? This includes every date and status, regardless of the current filters. Records will remain in the database but will no longer appear in SMS logs.') }}</flux:text>
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showClearLogsModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="danger" icon="trash" wire:loading.attr="disabled" wire:target="clearLogs">{{ __('Delete All') }}</flux:button>
            </div>
        </form>
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

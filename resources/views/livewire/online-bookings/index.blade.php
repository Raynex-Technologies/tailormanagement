<flux:main class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Online Bookings') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <flux:card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Online Bookings') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Review public booking requests before they become production work.') }}</flux:text>
            </div>
            <flux:badge color="lime">{{ number_format($bookings->total()) }} {{ __('requests') }}</flux:badge>
        </div>
    </flux:card>

    <flux:card>
        <div class="grid gap-4 md:grid-cols-6">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search reference, name, phone') }}" class="md:col-span-2" />
            <flux:select wire:model.live="status">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($statuses as $option)
                    <flux:select.option value="{{ $option }}">{{ str($option)->headline() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="bookingType">
                <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                @foreach ($types as $option)
                    <flux:select.option value="{{ $option }}">{{ str($option)->replace('_', ' ')->headline() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="branchId">
                <flux:select.option value="">{{ __('All branches') }}</flux:select.option>
                @foreach ($branches as $branch)
                    <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex items-center justify-between gap-3">
                <flux:checkbox wire:model.live="urgentOnly" label="{{ __('Urgent') }}" />
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
            </div>
        </div>
    </flux:card>

    <flux:card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <th class="px-4 py-3">{{ __('Reference') }}</th>
                        <th class="px-4 py-3">{{ __('Customer') }}</th>
                        <th class="px-4 py-3">{{ __('Type') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3">{{ __('Appointment') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($bookings as $booking)
                        <tr wire:key="online-booking-row-{{ $booking->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3 font-mono text-xs">{{ $booking->booking_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $booking->customer_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $booking->customer_phone }} @if($booking->branch) · {{ $booking->branch->name }} @endif</div>
                            </td>
                            <td class="px-4 py-3">{{ str($booking->booking_type)->replace('_', ' ')->headline() }}</td>
                            <td class="px-4 py-3"><flux:badge size="sm" color="{{ $booking->status === 'declined' ? 'red' : ($booking->status === 'confirmed' ? 'green' : 'amber') }}">{{ str($booking->status)->headline() }}</flux:badge></td>
                            <td class="px-4 py-3">{{ $booking->appointment?->scheduled_start_at?->format('M j, H:i') ?? '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <flux:button size="xs" variant="ghost" wire:click="selectBooking({{ $booking->id }})">{{ __('View') }}</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-zinc-500">{{ __('No booking requests found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings->hasPages())
            <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">{{ $bookings->links() }}</div>
        @endif
    </flux:card>

    <flux:modal wire:model="showDetailsModal" class="max-w-5xl">
        @if ($selectedBooking)
            <div wire:key="online-booking-details-{{ $selectedBooking->id }}" class="space-y-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $selectedBooking->booking_number }}</flux:heading>
                        <flux:text>{{ str($selectedBooking->booking_type)->replace('_', ' ')->headline() }} · {{ $selectedBooking->created_at->format('M j, Y H:i') }}</flux:text>
                    </div>
                    <flux:badge color="{{ $selectedBooking->status === 'declined' ? 'red' : ($selectedBooking->status === 'confirmed' ? 'green' : 'amber') }}">{{ str($selectedBooking->status)->headline() }}</flux:badge>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="font-semibold">{{ __('Customer') }}</div>
                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                            <div>{{ $selectedBooking->customer_name }}</div>
                            <div>{{ $selectedBooking->customer_phone }}</div>
                            <div>{{ $selectedBooking->customer_email ?: '-' }}</div>
                            <div>{{ $selectedBooking->customer_location ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="font-semibold">{{ __('Appointment') }}</div>
                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                            <div>{{ $selectedBooking->appointment?->type?->name ?? '-' }}</div>
                            <div>{{ $selectedBooking->appointment?->scheduled_start_at?->format('M j, Y H:i') ?? '-' }}</div>
                            <div>{{ $selectedBooking->appointment?->status ? str($selectedBooking->appointment->status)->headline() : '-' }}</div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="font-semibold">{{ __('Dates') }}</div>
                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                            <div>{{ __('Needed by') }}: {{ $selectedBooking->needed_by_date?->format('M j, Y') ?? '-' }}</div>
                            <div>{{ __('Event') }}: {{ $selectedBooking->event_date?->format('M j, Y') ?? '-' }}</div>
                            <div>{{ __('Urgent') }}: {{ $selectedBooking->is_urgent ? __('Yes') : __('No') }}</div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach ($selectedBooking->items as $item)
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="font-semibold">{{ $item->category?->name ?? $item->garment_name ?? __('Garment') }} × {{ $item->quantity }}</div>
                            <div class="mt-2 grid gap-2 text-sm text-zinc-600 dark:text-zinc-300 md:grid-cols-3">
                                <div>{{ __('Fabric') }}: {{ $item->fabric_source ?: '-' }} {{ $item->fabric_type ? '· '.$item->fabric_type : '' }}</div>
                                <div>{{ __('Color') }}: {{ $item->primary_color ?: '-' }}</div>
                                <div>{{ __('Fit') }}: {{ $item->preferred_fit ?: '-' }}</div>
                            </div>
                            @if ($item->selectedOptions->isNotEmpty())
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($item->selectedOptions as $selected)
                                        <flux:badge color="zinc" size="sm">{{ $selected->group?->name }}: {{ $selected->option?->label ?? $selected->custom_value }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($selectedBooking->notes || $selectedBooking->payload)
                    <div class="rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-800/60">
                        <div class="font-semibold">{{ __('Notes and flow data') }}</div>
                        <p class="mt-2 whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $selectedBooking->notes ?: '-' }}</p>
                    </div>
                @endif

                @can('online-bookings.review')
                    <form wire:submit="updateStatus" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="grid gap-4 md:grid-cols-3">
                            <flux:select wire:model="targetStatus">
                                @foreach ($statuses as $option)
                                    <flux:select.option value="{{ $option }}">{{ str($option)->headline() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="reviewNote" placeholder="{{ __('Review note or decline reason') }}" class="md:col-span-2" />
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <flux:button type="button" variant="ghost" wire:click="closeDetails">{{ __('Close') }}</flux:button>
                            <flux:button type="submit" variant="primary">{{ __('Update Status') }}</flux:button>
                        </div>
                    </form>
                @endcan
            </div>
        @endif
    </flux:modal>
</flux:main>

<flux:main class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Appointments') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <flux:card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Appointments') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Approve, decline, reschedule, and complete appointment requests.') }}</flux:text>
            </div>
            <flux:badge color="lime">{{ number_format($appointments->total()) }} {{ __('appointments') }}</flux:badge>
        </div>
    </flux:card>

    <flux:card>
        <div class="grid gap-4 md:grid-cols-6">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search reference, name, phone') }}" class="md:col-span-2" />
            <flux:input type="date" wire:model.live="date" />
            <flux:select wire:model.live="status">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($statuses as $option)
                    <flux:select.option value="{{ $option }}">{{ str($option)->headline() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="appointmentTypeId">
                <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                @foreach ($types as $type)
                    <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="branchId">
                <flux:select.option value="">{{ __('All branches') }}</flux:select.option>
                @foreach ($branches as $branch)
                    <flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>
                @endforeach
            </flux:select>
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
                        <th class="px-4 py-3">{{ __('Time') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($appointments as $appointment)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3 font-mono text-xs">{{ $appointment->appointment_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $appointment->customer_name ?: '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $appointment->customer_phone ?: '-' }} @if($appointment->branch) · {{ $appointment->branch->name }} @endif</div>
                            </td>
                            <td class="px-4 py-3">{{ $appointment->type?->name }}</td>
                            <td class="px-4 py-3">{{ $appointment->scheduled_start_at->format('M j, H:i') }}</td>
                            <td class="px-4 py-3"><flux:badge size="sm" color="{{ $appointment->status === 'confirmed' ? 'green' : ($appointment->status === 'declined' || $appointment->status === 'cancelled' ? 'red' : 'amber') }}">{{ str($appointment->status)->headline() }}</flux:badge></td>
                            <td class="px-4 py-3 text-right"><flux:button size="xs" variant="ghost" wire:click="selectAppointment({{ $appointment->id }})">{{ __('Manage') }}</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-zinc-500">{{ __('No appointments found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($appointments->hasPages())
            <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">{{ $appointments->links() }}</div>
        @endif
    </flux:card>

    <flux:modal wire:model="selectedAppointmentId" class="max-w-4xl">
        @if ($selectedAppointment)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $selectedAppointment->appointment_number }}</flux:heading>
                        <flux:text>{{ $selectedAppointment->type?->name }} · {{ $selectedAppointment->scheduled_start_at->format('M j, Y H:i') }}</flux:text>
                    </div>
                    <flux:badge color="{{ $selectedAppointment->status === 'confirmed' ? 'green' : ($selectedAppointment->status === 'declined' || $selectedAppointment->status === 'cancelled' ? 'red' : 'amber') }}">{{ str($selectedAppointment->status)->headline() }}</flux:badge>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="font-semibold">{{ __('Customer') }}</div>
                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $selectedAppointment->customer_name }}<br>{{ $selectedAppointment->customer_phone }}<br>{{ $selectedAppointment->customer_email ?: '-' }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="font-semibold">{{ __('Schedule') }}</div>
                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $selectedAppointment->scheduled_start_at->format('M j, Y H:i') }}<br>{{ $selectedAppointment->scheduled_end_at->format('H:i') }}<br>{{ $selectedAppointment->branch?->name ?? __('Global') }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="font-semibold">{{ __('Booking') }}</div>
                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $selectedAppointment->booking?->booking_number ?? '-' }}<br>{{ $selectedAppointment->customer_note ?: '-' }}</div>
                    </div>
                </div>

                <flux:textarea wire:model="actionNote" rows="2" placeholder="{{ __('Reason or internal note') }}" />

                <div class="flex flex-wrap gap-2">
                    @can('appointments.approve')
                        <flux:button wire:click="approve" variant="primary">{{ __('Approve') }}</flux:button>
                    @endcan
                    @can('appointments.decline')
                        <flux:button wire:click="decline" variant="danger">{{ __('Decline') }}</flux:button>
                    @endcan
                    @can('appointments.cancel')
                        <flux:button wire:click="cancel" variant="ghost" wire:confirm="{{ __('Cancel this appointment?') }}">{{ __('Cancel') }}</flux:button>
                    @endcan
                    @can('appointments.complete')
                        <flux:button wire:click="complete" variant="ghost">{{ __('Mark Completed') }}</flux:button>
                    @endcan
                </div>

                @can('appointments.reschedule')
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="mb-3 font-semibold">{{ __('Reschedule') }}</div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <flux:input type="date" wire:model.live="rescheduleDate" />
                            <flux:select wire:model="selectedSlot" class="md:col-span-2">
                                <flux:select.option value="">{{ __('Choose available slot') }}</flux:select.option>
                                @foreach ($availableSlots as $slot)
                                    <flux:select.option value="{{ $slot['start_at'] }}">{{ $slot['label'] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        @if (empty($availableSlots))
                            <flux:text class="mt-2 text-sm text-amber-600">{{ __('No slots are available for this date. Choose another date.') }}</flux:text>
                        @endif
                        <div class="mt-4 flex justify-end">
                            <flux:button wire:click="reschedule" variant="primary">{{ __('Reschedule') }}</flux:button>
                        </div>
                    </div>
                @endcan

                <div class="flex justify-end">
                    <flux:button type="button" variant="ghost" wire:click="closeDetails">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</flux:main>

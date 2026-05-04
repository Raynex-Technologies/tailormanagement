<flux:main class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item>{{ __('Availability Settings') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <flux:card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Availability Settings') }}</flux:heading>
                <flux:text class="text-zinc-500">{{ __('Control public booking slots, appointment rules, and office closures.') }}</flux:text>
            </div>
            <flux:badge color="lime">{{ __('Africa/Dar_es_Salaam') }}</flux:badge>
        </div>
    </flux:card>

    <div class="flex flex-wrap gap-2">
        @foreach (['windows' => 'Weekly Availability', 'blocks' => 'Unavailable Times', 'types' => 'Appointment Types', 'preview' => 'Preview'] as $key => $label)
            <flux:button variant="{{ $tab === $key ? 'primary' : 'ghost' }}" wire:click="$set('tab', '{{ $key }}')">{{ __($label) }}</flux:button>
        @endforeach
    </div>

    @if ($tab === 'windows')
        <div class="grid gap-6 xl:grid-cols-3">
            <flux:card class="xl:col-span-1">
                <flux:heading size="lg">{{ $windowId ? __('Edit Window') : __('Add Window') }}</flux:heading>
                <form wire:submit="saveWindow" class="mt-4 space-y-4">
                    <flux:select wire:model="windowBranchId">
                        <flux:select.option value="">{{ __('Global / all branches') }}</flux:select.option>
                        @foreach ($branches as $branch)<flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:select wire:model="windowAppointmentTypeId">
                        <flux:select.option value="">{{ __('All appointment types') }}</flux:select.option>
                        @foreach ($types as $type)<flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:select wire:model="dayOfWeek">
                        @foreach ($days as $value => $label)<flux:select.option value="{{ $value }}">{{ __($label) }}</flux:select.option>@endforeach
                    </flux:select>
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input type="time" wire:model="startTime" />
                        <flux:input type="time" wire:model="endTime" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input type="number" min="5" wire:model="slotIntervalMinutes" placeholder="{{ __('Interval') }}" />
                        <flux:input type="number" min="1" wire:model="capacity" placeholder="{{ __('Capacity') }}" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input type="date" wire:model="effectiveFrom" />
                        <flux:input type="date" wire:model="effectiveUntil" />
                    </div>
                    <flux:checkbox wire:model="windowActive" label="{{ __('Active') }}" />
                    <div class="flex justify-end gap-2">
                        <flux:button type="button" variant="ghost" wire:click="resetWindow">{{ __('Reset') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:card>

            <flux:card class="xl:col-span-2">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead><tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500"><th class="px-4 py-3">{{ __('Scope') }}</th><th class="px-4 py-3">{{ __('Day') }}</th><th class="px-4 py-3">{{ __('Time') }}</th><th class="px-4 py-3">{{ __('Capacity') }}</th><th class="px-4 py-3 text-right">{{ __('Action') }}</th></tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($windows as $window)
                                <tr>
                                    <td class="px-4 py-3">{{ $window->branch?->name ?? __('Global') }}<br><span class="text-xs text-zinc-500">{{ $window->appointmentType?->name ?? __('All types') }}</span></td>
                                    <td class="px-4 py-3">{{ __($days[$window->day_of_week] ?? '-') }}</td>
                                    <td class="px-4 py-3">{{ substr($window->start_time, 0, 5) }} - {{ substr($window->end_time, 0, 5) }}</td>
                                    <td class="px-4 py-3">{{ $window->capacity }} / {{ $window->slot_interval_minutes }}m</td>
                                    <td class="px-4 py-3 text-right"><flux:button size="xs" variant="ghost" wire:click="editWindow({{ $window->id }})">{{ __('Edit') }}</flux:button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-12 text-center text-zinc-500">{{ __('No availability windows configured.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </flux:card>
        </div>
    @elseif ($tab === 'blocks')
        <div class="grid gap-6 xl:grid-cols-3">
            <flux:card>
                <flux:heading size="lg">{{ $blockId ? __('Edit Block') : __('Add Block') }}</flux:heading>
                <form wire:submit="saveBlock" class="mt-4 space-y-4">
                    <flux:input wire:model="blockTitle" placeholder="{{ __('Public holiday, staff training...') }}" />
                    <flux:select wire:model="blockBranchId"><flux:select.option value="">{{ __('Global / all branches') }}</flux:select.option>@foreach ($branches as $branch)<flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model="blockAppointmentTypeId"><flux:select.option value="">{{ __('All appointment types') }}</flux:select.option>@foreach ($types as $type)<flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>@endforeach</flux:select>
                    <div class="grid grid-cols-2 gap-3"><flux:input type="datetime-local" wire:model="startsAt" /><flux:input type="datetime-local" wire:model="endsAt" /></div>
                    <flux:textarea wire:model="blockReason" rows="2" placeholder="{{ __('Reason') }}" />
                    <div class="flex gap-4"><flux:checkbox wire:model="isFullDay" label="{{ __('Full day') }}" /><flux:checkbox wire:model="repeatsYearly" label="{{ __('Repeats yearly') }}" /></div>
                    <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="resetBlock">{{ __('Reset') }}</flux:button><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
                </form>
            </flux:card>
            <flux:card class="xl:col-span-2">
                <div class="space-y-3">
                    @forelse ($blocks as $block)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div>
                                <div class="font-medium">{{ $block->title }}</div>
                                <div class="text-sm text-zinc-500">{{ $block->starts_at->format('M j, Y H:i') }} - {{ $block->ends_at->format('M j, Y H:i') }} · {{ $block->branch?->name ?? __('Global') }} · {{ $block->appointmentType?->name ?? __('All types') }}</div>
                            </div>
                            <flux:button size="xs" variant="ghost" wire:click="editBlock({{ $block->id }})">{{ __('Edit') }}</flux:button>
                        </div>
                    @empty
                        <div class="py-12 text-center text-zinc-500">{{ __('No unavailable periods configured.') }}</div>
                    @endforelse
                </div>
            </flux:card>
        </div>
    @elseif ($tab === 'types')
        <flux:card>
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($types as $type)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex items-start justify-between gap-3">
                            <div><div class="font-semibold">{{ $type->name }}</div><div class="text-sm text-zinc-500">{{ $type->code }}</div></div>
                            <flux:badge color="{{ $type->is_active ? 'green' : 'zinc' }}">{{ $type->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3">
                            <flux:input type="number" min="5" value="{{ $type->default_duration_minutes }}" wire:change="saveAppointmentType({{ $type->id }}, 'default_duration_minutes', $event.target.value)" />
                            <flux:input type="number" min="0" value="{{ $type->buffer_before_minutes }}" wire:change="saveAppointmentType({{ $type->id }}, 'buffer_before_minutes', $event.target.value)" />
                            <flux:input type="number" min="0" value="{{ $type->buffer_after_minutes }}" wire:change="saveAppointmentType({{ $type->id }}, 'buffer_after_minutes', $event.target.value)" />
                        </div>
                        <div class="mt-4 flex gap-4">
                            <flux:checkbox :checked="$type->requires_approval" wire:change="saveAppointmentType({{ $type->id }}, 'requires_approval', $event.target.checked)" label="{{ __('Requires approval') }}" />
                            <flux:checkbox :checked="$type->is_public" wire:change="saveAppointmentType({{ $type->id }}, 'is_public', $event.target.checked)" label="{{ __('Public') }}" />
                            <flux:checkbox :checked="$type->is_active" wire:change="saveAppointmentType({{ $type->id }}, 'is_active', $event.target.checked)" label="{{ __('Active') }}" />
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @else
        <flux:card>
            <div class="grid gap-4 md:grid-cols-3">
                <flux:select wire:model.live="previewBranchId"><flux:select.option value="">{{ __('Global / default') }}</flux:select.option>@foreach ($branches as $branch)<flux:select.option value="{{ $branch->id }}">{{ $branch->name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="previewAppointmentTypeId">@foreach ($types as $type)<flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>@endforeach</flux:select>
                <flux:input type="date" wire:model.live="previewDate" />
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($previewSlots as $slot)
                    <div class="rounded-lg border p-3 {{ $slot['available'] ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200' : 'border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50' }}">
                        <div class="font-medium">{{ $slot['label'] }}</div>
                        <div class="text-xs">{{ $slot['available'] ? __('Available') : str($slot['reason'])->headline() }}</div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center text-zinc-500">{{ __('No slots generated for this date.') }}</div>
                @endforelse
            </div>
        </flux:card>
    @endif
</flux:main>

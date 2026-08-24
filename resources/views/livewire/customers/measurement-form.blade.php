<flux:main class="mx-auto max-w-5xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate />
        <flux:breadcrumbs.item href="{{ route('customers.index') }}" wire:navigate>{{ __('Customers') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item href="{{ route('customers.show', $customer) }}" wire:navigate>{{ $customer->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $isUpdate ? __('Update Measurements') : __('Record Measurements') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div>
        <flux:heading size="xl">{{ $isUpdate ? __('Update Customer Measurements') : __('Record Customer Measurements') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            {{ $isUpdate
                ? __('Saving creates a new revision. The existing revision remains unchanged.')
                : __('Create the first saved measurement profile for :customer.', ['customer' => $customer->name]) }}
        </flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <flux:input type="date" wire:model="measuredAt" label="{{ __('Measured on') }}" required />
                    @error('measuredAt')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <flux:textarea wire:model="notes" label="{{ __('Revision notes') }}" rows="2" placeholder="{{ __('Optional fit context or measuring notes') }}" />
                    @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Measurements') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        {{ __('Values are saved exactly in the unit selected; no automatic conversion is applied.') }}
                    </flux:text>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:min-w-80 sm:flex-row">
                    <div class="min-w-0 flex-1">
                        <flux:select wire:model="selectedMeasurementFieldId" aria-label="{{ __('Measurement to add') }}">
                            <flux:select.option value="">{{ __('Select measurement…') }}</flux:select.option>
                            @foreach ($availableFields as $field)
                                <flux:select.option value="{{ $field->id }}">{{ $field->name }} ({{ $field->code }})</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button type="button" variant="subtle" wire:click="addMeasurement">
                        {{ __('Add') }}
                    </flux:button>
                </div>
            </div>
            @error('selectedMeasurementFieldId')<p class="mt-2 text-sm text-red-600 sm:text-right">{{ $message }}</p>@enderror
            @error('rows')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror

            @if ($rows === [])
                <div class="mt-5 rounded-xl border border-dashed border-zinc-300 px-5 py-8 text-center dark:border-white/15">
                    <flux:text class="font-medium">{{ __('No measurements added yet.') }}</flux:text>
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Select an active definition above to begin.') }}</flux:text>
                </div>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                <th class="px-3 py-3">{{ __('Measurement') }}</th>
                                <th class="w-44 px-3 py-3">{{ __('Value') }}</th>
                                <th class="w-36 px-3 py-3">{{ __('Unit') }}</th>
                                <th class="w-20 px-3 py-3 text-right">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-white/10">
                            @foreach ($rows as $index => $row)
                                <tr wire:key="measurement-row-{{ $row['measurement_field_id'] }}" class="align-top">
                                    <td class="px-3 py-4">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row['label'] }}</div>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-zinc-500">
                                            <span class="font-mono">{{ $row['code'] }}</span>
                                            @if ($row['archived'])
                                                <flux:badge color="zinc" size="sm">{{ __('Archived') }}</flux:badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-4">
                                        <flux:input
                                            type="text"
                                            inputmode="decimal"
                                            wire:model="rows.{{ $index }}.value"
                                            aria-label="{{ __('Value for :measurement', ['measurement' => $row['label']]) }}"
                                            placeholder="0.00"
                                        />
                                        @error("rows.$index.value")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </td>
                                    <td class="px-3 py-4">
                                        <flux:select wire:model="rows.{{ $index }}.unit" aria-label="{{ __('Unit for :measurement', ['measurement' => $row['label']]) }}">
                                            <flux:select.option value="cm">cm</flux:select.option>
                                            <flux:select.option value="in">in</flux:select.option>
                                            <flux:select.option value="kg">kg</flux:select.option>
                                        </flux:select>
                                        @error("rows.$index.unit")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </td>
                                    <td class="px-3 py-4 text-right">
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="removeMeasurement({{ $index }})">
                                            {{ __('Remove') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:button type="button" variant="ghost" :href="route('customers.show', $customer)" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $isUpdate ? __('Save New Revision') : __('Record Measurements') }}</span>
                <span wire:loading>{{ __('Saving…') }}</span>
            </flux:button>
        </div>
    </form>
</flux:main>

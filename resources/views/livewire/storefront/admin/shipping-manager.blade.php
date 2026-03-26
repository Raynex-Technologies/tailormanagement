<div>
    <flux:main class="p-0">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Storefront') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Shipping') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl">{{ __('Storefront Shipping Manager') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('Configure global shipping zones, delivery methods, and product shipping profiles.') }}
            </flux:text>
        </div>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <div class="mt-6 flex flex-wrap gap-2 border-b border-zinc-200 dark:border-zinc-700">
            @foreach (['zones' => 'Zones', 'methods' => 'Methods', 'profiles' => 'Profiles'] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition {{ $tab === $key ? 'border-b-2 border-lime-500 bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
                >
                    {{ __($label) }}
                </button>
            @endforeach
        </div>

        @if ($tab === 'zones')
            <div class="mt-6 grid gap-6 xl:grid-cols-[430px_1fr]">
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ $editingZoneId ? __('Edit Shipping Zone') : __('Create Shipping Zone') }}</flux:heading>
                    <flux:input wire:model.blur="zoneName" label="{{ __('Zone Name') }}" required />
                    <flux:textarea wire:model.blur="zoneCountries" rows="2" label="{{ __('Countries (CSV, ISO2/ISO3)') }}" placeholder="US, KE, TZ" />
                    <flux:textarea wire:model.blur="zoneRegions" rows="2" label="{{ __('States/Regions (CSV)') }}" placeholder="California, Nairobi County" />
                    <flux:textarea wire:model.blur="zonePostalCodes" rows="2" label="{{ __('Postal Patterns (CSV, wildcard allowed)') }}" placeholder="90*, SW1A*" />
                    <flux:input wire:model.blur="zoneSort" type="number" min="0" label="{{ __('Sort Order') }}" />

                    <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                        <span>{{ __('Active') }}</span>
                        <input type="checkbox" wire:model="zoneActive" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                    </label>

                    <div class="flex justify-end gap-2">
                        @if ($editingZoneId)
                            <flux:button type="button" variant="ghost" wire:click="resetZoneForm">{{ __('Cancel') }}</flux:button>
                        @endif
                        <flux:button type="button" variant="primary" wire:click="saveZone">
                            {{ $editingZoneId ? __('Update Zone') : __('Create Zone') }}
                        </flux:button>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    <th class="px-4 py-3">{{ __('Zone') }}</th>
                                    <th class="px-4 py-3">{{ __('Coverage') }}</th>
                                    <th class="px-4 py-3">{{ __('Methods') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse ($zones as $zone)
                                    <tr class="text-sm">
                                        <td class="px-4 py-3 font-medium">{{ $zone->name }}</td>
                                        <td class="px-4 py-3 text-zinc-500">
                                            <div>{{ collect($zone->countries ?? [])->take(3)->implode(', ') ?: __('All countries') }}</div>
                                            @if (collect($zone->countries ?? [])->count() > 3)
                                                <div class="text-xs text-zinc-400">+{{ collect($zone->countries ?? [])->count() - 3 }} more</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $zone->methods_count }}</td>
                                        <td class="px-4 py-3">
                                            <flux:badge size="sm" :color="$zone->is_active ? 'green' : 'zinc'">
                                                {{ $zone->is_active ? __('Active') : __('Inactive') }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="editZone({{ $zone->id }})">{{ __('Edit') }}</flux:button>
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="deleteZone({{ $zone->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No shipping zones configured.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>
        @endif

        @if ($tab === 'methods')
            <div class="mt-6 grid gap-6 xl:grid-cols-[430px_1fr]">
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ $editingMethodId ? __('Edit Shipping Method') : __('Create Shipping Method') }}</flux:heading>

                    <flux:select wire:model="methodZoneId" label="{{ __('Zone (optional)') }}">
                        <flux:select.option value="">{{ __('Global / Any Zone') }}</flux:select.option>
                        @foreach ($zoneOptions as $zone)
                            <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model.blur="methodName" label="{{ __('Method Name') }}" required />
                    <flux:input wire:model.blur="methodCode" label="{{ __('Method Code') }}" placeholder="express_delivery" required />
                    <flux:select wire:model="methodType" label="{{ __('Method Type') }}">
                        @foreach ($methodTypes as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model.blur="methodAmount" type="number" step="0.01" min="0" label="{{ __('Base Amount') }}" />
                        <flux:input wire:model.blur="methodCurrency" maxlength="3" label="{{ __('Currency') }}" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model.blur="methodMinSubtotal" type="number" step="0.01" min="0" label="{{ __('Min Subtotal') }}" />
                        <flux:input wire:model.blur="methodMaxSubtotal" type="number" step="0.01" min="0" label="{{ __('Max Subtotal') }}" />
                        <flux:input wire:model.blur="methodMinWeight" type="number" step="0.001" min="0" label="{{ __('Min Weight') }}" />
                        <flux:input wire:model.blur="methodMaxWeight" type="number" step="0.001" min="0" label="{{ __('Max Weight') }}" />
                        <flux:input wire:model.blur="methodMinItems" type="number" min="0" label="{{ __('Min Items') }}" />
                        <flux:input wire:model.blur="methodMaxItems" type="number" min="0" label="{{ __('Max Items') }}" />
                    </div>

                    <flux:input wire:model.blur="methodFreeShippingThreshold" type="number" step="0.01" min="0" label="{{ __('Free Shipping Threshold') }}" />
                    <flux:input wire:model.blur="methodEstimatedDeliveryWindow" label="{{ __('Estimated Delivery Window') }}" placeholder="2-5 business days" />
                    <flux:textarea wire:model.blur="methodSettingsJson" rows="4" label="{{ __('Method Settings JSON') }}" />
                    <flux:input wire:model.blur="methodSort" type="number" min="0" label="{{ __('Sort Order') }}" />

                    <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                        <span>{{ __('Active') }}</span>
                        <input type="checkbox" wire:model="methodActive" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                    </label>

                    <div class="flex justify-end gap-2">
                        @if ($editingMethodId)
                            <flux:button type="button" variant="ghost" wire:click="resetMethodForm">{{ __('Cancel') }}</flux:button>
                        @endif
                        <flux:button type="button" variant="primary" wire:click="saveMethod">
                            {{ $editingMethodId ? __('Update Method') : __('Create Method') }}
                        </flux:button>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    <th class="px-4 py-3">{{ __('Method') }}</th>
                                    <th class="px-4 py-3">{{ __('Zone') }}</th>
                                    <th class="px-4 py-3">{{ __('Type') }}</th>
                                    <th class="px-4 py-3">{{ __('Amount') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse ($methods as $method)
                                    <tr class="text-sm">
                                        <td class="px-4 py-3">
                                            <div class="font-medium">{{ $method->name }}</div>
                                            <div class="text-xs text-zinc-500">{{ $method->code }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-zinc-500">{{ $method->zone?->name ?: __('Global') }}</td>
                                        <td class="px-4 py-3 text-zinc-500">{{ str($method->type?->value ?: 'flat_rate')->replace('_', ' ')->title() }}</td>
                                        <td class="px-4 py-3">{{ money_currency($method->amount, $method->currency) }}</td>
                                        <td class="px-4 py-3">
                                            <flux:badge size="sm" :color="$method->is_active ? 'green' : 'zinc'">
                                                {{ $method->is_active ? __('Active') : __('Inactive') }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="editMethod({{ $method->id }})">{{ __('Edit') }}</flux:button>
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="deleteMethod({{ $method->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No shipping methods configured.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>
        @endif

        @if ($tab === 'profiles')
            <div class="mt-6 grid gap-6 xl:grid-cols-[430px_1fr]">
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ $editingProfileId ? __('Edit Shipping Profile') : __('Create Shipping Profile') }}</flux:heading>
                    <flux:input wire:model.blur="profileName" label="{{ __('Profile Name') }}" required />
                    <flux:textarea wire:model.blur="profileDescription" rows="3" label="{{ __('Description') }}" />
                    <flux:input wire:model.blur="profileHandlingFee" type="number" step="0.01" min="0" label="{{ __('Handling Fee') }}" />

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                            <span>{{ __('Default Profile') }}</span>
                            <input type="checkbox" wire:model="profileDefault" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                            <span>{{ __('Active') }}</span>
                            <input type="checkbox" wire:model="profileActive" class="rounded border-zinc-300 text-lime-600 focus:ring-lime-500" />
                        </label>
                    </div>

                    <div class="flex justify-end gap-2">
                        @if ($editingProfileId)
                            <flux:button type="button" variant="ghost" wire:click="resetProfileForm">{{ __('Cancel') }}</flux:button>
                        @endif
                        <flux:button type="button" variant="primary" wire:click="saveProfile">
                            {{ $editingProfileId ? __('Update Profile') : __('Create Profile') }}
                        </flux:button>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    <th class="px-4 py-3">{{ __('Profile') }}</th>
                                    <th class="px-4 py-3">{{ __('Handling') }}</th>
                                    <th class="px-4 py-3">{{ __('Items') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse ($profiles as $profile)
                                    <tr class="text-sm">
                                        <td class="px-4 py-3">
                                            <div class="font-medium">{{ $profile->name }}</div>
                                            @if ($profile->description)
                                                <div class="text-xs text-zinc-500">{{ $profile->description }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ money_currency($profile->handling_fee, config('app.currency', 'TZS')) }}</td>
                                        <td class="px-4 py-3">{{ $profile->items_count }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex gap-1">
                                                <flux:badge size="sm" :color="$profile->is_active ? 'green' : 'zinc'">
                                                    {{ $profile->is_active ? __('Active') : __('Inactive') }}
                                                </flux:badge>
                                                @if ($profile->is_default)
                                                    <flux:badge size="sm" color="blue">{{ __('Default') }}</flux:badge>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="editProfile({{ $profile->id }})">{{ __('Edit') }}</flux:button>
                                                <flux:button type="button" size="sm" variant="ghost" wire:click="deleteProfile({{ $profile->id }})" class="text-red-600">{{ __('Delete') }}</flux:button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No shipping profiles configured.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>
        @endif
    </flux:main>
</div>

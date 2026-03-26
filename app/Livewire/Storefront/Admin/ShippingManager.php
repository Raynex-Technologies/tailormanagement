<?php

namespace App\Livewire\Storefront\Admin;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use App\Models\ShippingProfile;
use App\Models\ShippingZone;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Storefront Shipping')]
class ShippingManager extends Component
{
    public string $tab = 'zones';

    public ?int $editingZoneId = null;
    public string $zoneName = '';
    public string $zoneCountries = '';
    public string $zoneRegions = '';
    public string $zonePostalCodes = '';
    public bool $zoneActive = true;
    public int $zoneSort = 0;

    public ?int $editingMethodId = null;
    public ?int $methodZoneId = null;
    public string $methodCode = '';
    public string $methodName = '';
    public string $methodType = 'flat_rate';
    public ?float $methodAmount = 0;
    public string $methodCurrency = 'TZS';
    public ?float $methodMinSubtotal = null;
    public ?float $methodMaxSubtotal = null;
    public ?float $methodMinWeight = null;
    public ?float $methodMaxWeight = null;
    public ?int $methodMinItems = null;
    public ?int $methodMaxItems = null;
    public ?float $methodFreeShippingThreshold = null;
    public string $methodEstimatedDeliveryWindow = '';
    public string $methodSettingsJson = '';
    public bool $methodActive = true;
    public int $methodSort = 0;

    public ?int $editingProfileId = null;
    public string $profileName = '';
    public string $profileDescription = '';
    public ?float $profileHandlingFee = 0;
    public bool $profileDefault = false;
    public bool $profileActive = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('storefront.shipping.manage'), 403);
    }

    public function saveZone(): void
    {
        $this->authorize('storefront.shipping.manage');

        $nameRule = Rule::unique('shipping_zones', 'name');
        if ($this->editingZoneId) {
            $nameRule = $nameRule->ignore($this->editingZoneId);
        }

        $validated = $this->validate([
            'zoneName' => ['required', 'string', 'max:191', $nameRule],
            'zoneCountries' => ['nullable', 'string', 'max:5000'],
            'zoneRegions' => ['nullable', 'string', 'max:5000'],
            'zonePostalCodes' => ['nullable', 'string', 'max:5000'],
            'zoneActive' => ['boolean'],
            'zoneSort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $payload = [
            'name' => trim($validated['zoneName']),
            'countries' => $this->splitList($validated['zoneCountries'], true),
            'regions' => $this->splitList($validated['zoneRegions']),
            'postal_codes' => $this->splitList($validated['zonePostalCodes']),
            'is_active' => (bool) $validated['zoneActive'],
            'sort_order' => (int) ($validated['zoneSort'] ?? 0),
        ];

        if ($this->editingZoneId) {
            ShippingZone::query()->whereKey($this->editingZoneId)->update($payload);
            session()->flash('success', 'Shipping zone updated.');
        } else {
            ShippingZone::query()->create($payload);
            session()->flash('success', 'Shipping zone created.');
        }

        $this->resetZoneForm();
    }

    public function editZone(int $id): void
    {
        $this->authorize('storefront.shipping.manage');

        $zone = ShippingZone::query()->findOrFail($id);

        $this->editingZoneId = $zone->id;
        $this->zoneName = $zone->name;
        $this->zoneCountries = collect($zone->countries ?? [])->implode(', ');
        $this->zoneRegions = collect($zone->regions ?? [])->implode(', ');
        $this->zonePostalCodes = collect($zone->postal_codes ?? [])->implode(', ');
        $this->zoneActive = (bool) $zone->is_active;
        $this->zoneSort = (int) ($zone->sort_order ?? 0);
        $this->tab = 'zones';
    }

    public function deleteZone(int $id): void
    {
        $this->authorize('storefront.shipping.manage');

        ShippingZone::query()->whereKey($id)->delete();

        if ($this->editingZoneId === $id) {
            $this->resetZoneForm();
        }

        session()->flash('success', 'Shipping zone deleted.');
    }

    public function resetZoneForm(): void
    {
        $this->editingZoneId = null;
        $this->zoneName = '';
        $this->zoneCountries = '';
        $this->zoneRegions = '';
        $this->zonePostalCodes = '';
        $this->zoneActive = true;
        $this->zoneSort = 0;
        $this->resetErrorBag([
            'zoneName',
            'zoneCountries',
            'zoneRegions',
            'zonePostalCodes',
            'zoneSort',
        ]);
    }

    public function saveMethod(): void
    {
        $this->authorize('storefront.shipping.manage');

        $codeRule = Rule::unique('shipping_methods', 'code');
        if ($this->editingMethodId) {
            $codeRule = $codeRule->ignore($this->editingMethodId);
        }

        $validated = $this->validate([
            'methodZoneId' => ['nullable', 'integer', 'exists:shipping_zones,id'],
            'methodCode' => ['required', 'string', 'max:100', $codeRule],
            'methodName' => ['required', 'string', 'max:191'],
            'methodType' => ['required', Rule::in(array_column(ShippingMethodType::cases(), 'value'))],
            'methodAmount' => ['nullable', 'numeric', 'min:0'],
            'methodCurrency' => ['required', 'string', 'size:3'],
            'methodMinSubtotal' => ['nullable', 'numeric', 'min:0'],
            'methodMaxSubtotal' => ['nullable', 'numeric', 'min:0'],
            'methodMinWeight' => ['nullable', 'numeric', 'min:0'],
            'methodMaxWeight' => ['nullable', 'numeric', 'min:0'],
            'methodMinItems' => ['nullable', 'integer', 'min:0'],
            'methodMaxItems' => ['nullable', 'integer', 'min:0'],
            'methodFreeShippingThreshold' => ['nullable', 'numeric', 'min:0'],
            'methodEstimatedDeliveryWindow' => ['nullable', 'string', 'max:191'],
            'methodSettingsJson' => ['nullable', 'string'],
            'methodActive' => ['boolean'],
            'methodSort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $settings = null;
        if (trim($validated['methodSettingsJson']) !== '') {
            $decoded = json_decode($validated['methodSettingsJson'], true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'methodSettingsJson' => 'Method settings JSON must be a valid JSON object or array.',
                ]);
            }
            $settings = $decoded;
        }

        $payload = [
            'shipping_zone_id' => $validated['methodZoneId'] ?: null,
            'code' => Str::slug($validated['methodCode'], '_'),
            'name' => trim($validated['methodName']),
            'type' => $validated['methodType'],
            'amount' => $validated['methodAmount'] ?? 0,
            'currency' => strtoupper($validated['methodCurrency']),
            'min_subtotal' => $validated['methodMinSubtotal'] !== '' ? $validated['methodMinSubtotal'] : null,
            'max_subtotal' => $validated['methodMaxSubtotal'] !== '' ? $validated['methodMaxSubtotal'] : null,
            'min_weight' => $validated['methodMinWeight'] !== '' ? $validated['methodMinWeight'] : null,
            'max_weight' => $validated['methodMaxWeight'] !== '' ? $validated['methodMaxWeight'] : null,
            'min_items' => $validated['methodMinItems'],
            'max_items' => $validated['methodMaxItems'],
            'free_shipping_threshold' => $validated['methodFreeShippingThreshold'] !== '' ? $validated['methodFreeShippingThreshold'] : null,
            'estimated_delivery_window' => $validated['methodEstimatedDeliveryWindow'] ?: null,
            'settings' => $settings,
            'is_active' => (bool) $validated['methodActive'],
            'sort_order' => (int) ($validated['methodSort'] ?? 0),
        ];

        if ($this->editingMethodId) {
            ShippingMethod::query()->whereKey($this->editingMethodId)->update($payload);
            session()->flash('success', 'Shipping method updated.');
        } else {
            ShippingMethod::query()->create($payload);
            session()->flash('success', 'Shipping method created.');
        }

        $this->resetMethodForm();
    }

    public function editMethod(int $id): void
    {
        $this->authorize('storefront.shipping.manage');

        $method = ShippingMethod::query()->findOrFail($id);

        $this->editingMethodId = $method->id;
        $this->methodZoneId = $method->shipping_zone_id;
        $this->methodCode = $method->code;
        $this->methodName = $method->name;
        $this->methodType = $method->type?->value ?: ShippingMethodType::FlatRate->value;
        $this->methodAmount = (float) ($method->amount ?? 0);
        $this->methodCurrency = strtoupper((string) ($method->currency ?: 'TZS'));
        $this->methodMinSubtotal = $method->min_subtotal !== null ? (float) $method->min_subtotal : null;
        $this->methodMaxSubtotal = $method->max_subtotal !== null ? (float) $method->max_subtotal : null;
        $this->methodMinWeight = $method->min_weight !== null ? (float) $method->min_weight : null;
        $this->methodMaxWeight = $method->max_weight !== null ? (float) $method->max_weight : null;
        $this->methodMinItems = $method->min_items;
        $this->methodMaxItems = $method->max_items;
        $this->methodFreeShippingThreshold = $method->free_shipping_threshold !== null ? (float) $method->free_shipping_threshold : null;
        $this->methodEstimatedDeliveryWindow = (string) ($method->estimated_delivery_window ?? '');
        $this->methodSettingsJson = $method->settings ? json_encode($method->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
        $this->methodActive = (bool) $method->is_active;
        $this->methodSort = (int) ($method->sort_order ?? 0);
        $this->tab = 'methods';
    }

    public function deleteMethod(int $id): void
    {
        $this->authorize('storefront.shipping.manage');

        ShippingMethod::query()->whereKey($id)->delete();

        if ($this->editingMethodId === $id) {
            $this->resetMethodForm();
        }

        session()->flash('success', 'Shipping method deleted.');
    }

    public function resetMethodForm(): void
    {
        $this->editingMethodId = null;
        $this->methodZoneId = null;
        $this->methodCode = '';
        $this->methodName = '';
        $this->methodType = ShippingMethodType::FlatRate->value;
        $this->methodAmount = 0;
        $this->methodCurrency = 'TZS';
        $this->methodMinSubtotal = null;
        $this->methodMaxSubtotal = null;
        $this->methodMinWeight = null;
        $this->methodMaxWeight = null;
        $this->methodMinItems = null;
        $this->methodMaxItems = null;
        $this->methodFreeShippingThreshold = null;
        $this->methodEstimatedDeliveryWindow = '';
        $this->methodSettingsJson = '';
        $this->methodActive = true;
        $this->methodSort = 0;

        $this->resetErrorBag([
            'methodZoneId',
            'methodCode',
            'methodName',
            'methodType',
            'methodAmount',
            'methodCurrency',
            'methodMinSubtotal',
            'methodMaxSubtotal',
            'methodMinWeight',
            'methodMaxWeight',
            'methodMinItems',
            'methodMaxItems',
            'methodFreeShippingThreshold',
            'methodEstimatedDeliveryWindow',
            'methodSettingsJson',
            'methodSort',
        ]);
    }

    public function saveProfile(): void
    {
        $this->authorize('storefront.shipping.manage');

        $nameRule = Rule::unique('shipping_profiles', 'name');
        if ($this->editingProfileId) {
            $nameRule = $nameRule->ignore($this->editingProfileId);
        }

        $validated = $this->validate([
            'profileName' => ['required', 'string', 'max:191', $nameRule],
            'profileDescription' => ['nullable', 'string', 'max:2000'],
            'profileHandlingFee' => ['nullable', 'numeric', 'min:0'],
            'profileDefault' => ['boolean'],
            'profileActive' => ['boolean'],
        ]);

        if ($validated['profileDefault']) {
            ShippingProfile::query()->update(['is_default' => false]);
        }

        $payload = [
            'name' => trim($validated['profileName']),
            'description' => $validated['profileDescription'] ?: null,
            'handling_fee' => $validated['profileHandlingFee'] ?? 0,
            'is_default' => (bool) $validated['profileDefault'],
            'is_active' => (bool) $validated['profileActive'],
        ];

        if ($this->editingProfileId) {
            ShippingProfile::query()->whereKey($this->editingProfileId)->update($payload);
            session()->flash('success', 'Shipping profile updated.');
        } else {
            ShippingProfile::query()->create($payload);
            session()->flash('success', 'Shipping profile created.');
        }

        $this->resetProfileForm();
    }

    public function editProfile(int $id): void
    {
        $this->authorize('storefront.shipping.manage');

        $profile = ShippingProfile::query()->findOrFail($id);

        $this->editingProfileId = $profile->id;
        $this->profileName = $profile->name;
        $this->profileDescription = (string) ($profile->description ?? '');
        $this->profileHandlingFee = (float) ($profile->handling_fee ?? 0);
        $this->profileDefault = (bool) $profile->is_default;
        $this->profileActive = (bool) $profile->is_active;
        $this->tab = 'profiles';
    }

    public function deleteProfile(int $id): void
    {
        $this->authorize('storefront.shipping.manage');

        ShippingProfile::query()->whereKey($id)->delete();

        if ($this->editingProfileId === $id) {
            $this->resetProfileForm();
        }

        session()->flash('success', 'Shipping profile deleted.');
    }

    public function resetProfileForm(): void
    {
        $this->editingProfileId = null;
        $this->profileName = '';
        $this->profileDescription = '';
        $this->profileHandlingFee = 0;
        $this->profileDefault = false;
        $this->profileActive = true;
        $this->resetErrorBag([
            'profileName',
            'profileDescription',
            'profileHandlingFee',
        ]);
    }

    public function render()
    {
        return view('livewire.storefront.admin.shipping-manager', [
            'zones' => ShippingZone::query()->withCount('methods')->orderBy('sort_order')->orderBy('name')->get(),
            'methods' => ShippingMethod::query()->with('zone')->orderBy('sort_order')->orderBy('name')->get(),
            'profiles' => ShippingProfile::query()->withCount('items')->orderByDesc('is_default')->orderBy('name')->get(),
            'zoneOptions' => ShippingZone::query()->orderBy('name')->get(['id', 'name']),
            'methodTypes' => collect(ShippingMethodType::cases())->mapWithKeys(fn (ShippingMethodType $type) => [$type->value => Str::headline($type->value)]),
        ]);
    }

    protected function splitList(?string $value, bool $uppercase = false): array
    {
        $list = collect(preg_split('/[\r\n,]+/', (string) $value) ?: [])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->unique()
            ->values();

        if ($uppercase) {
            return $list->map(fn ($part) => strtoupper($part))->values()->all();
        }

        return $list->all();
    }
}


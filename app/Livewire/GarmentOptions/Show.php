<?php

namespace App\Livewire\GarmentOptions;

use App\Models\GarmentCategory;
use App\Models\MeasurementField;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

#[Layout('layouts.app.sidebar')]
class Show extends CategoriesIndex
{
    #[Locked]
    public int $garmentTypeId;

    #[Url]
    public string $tab = 'overview';

    public bool $managingMeasurements = false;

    public string $measurementSearch = '';

    public array $measurementSettings = [];

    public function mount(?GarmentCategory $garmentCategory = null): void
    {
        parent::mount();
        abort_unless($garmentCategory?->exists, 404);
        $this->garmentTypeId = $garmentCategory->id;
        $this->setTab($this->tab);
    }

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab, ['overview', 'measurements', 'customization', 'items'], true), 404);
        if ($tab === 'items') {
            $this->authorize('order_catalog.view');
        }
        $this->tab = $tab;
    }

    public function manageMeasurements(): void
    {
        $this->authorize('measurement_fields.manage');
        $this->measurementSettings = $this->garmentType()->measurementFields->mapWithKeys(fn ($field) => [
            $field->id => ['selected' => true, 'required' => (bool) $field->pivot->is_required, 'sort_order' => (int) $field->pivot->sort_order],
        ])->all();
        $this->measurementSearch = '';
        $this->managingMeasurements = true;
    }

    public function selectMeasurement(int $fieldId): void
    {
        $this->authorize('measurement_fields.manage');
        MeasurementField::query()->active()->findOrFail($fieldId);
        $this->measurementSettings[$fieldId] ??= ['selected' => true, 'required' => false, 'sort_order' => 0];
        $this->measurementSettings[$fieldId]['selected'] = true;
    }

    public function saveMeasurements(): void
    {
        $this->authorize('measurement_fields.manage');
        $this->validate([
            'measurementSettings' => ['array'],
            'measurementSettings.*.selected' => ['required', 'boolean'],
            'measurementSettings.*.required' => ['required', 'boolean'],
            'measurementSettings.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
        DB::transaction(function (): void {
            $category = GarmentCategory::query()->lockForUpdate()->findOrFail($this->garmentTypeId);
            $existing = $category->measurementFields()->pluck('measurement_fields.id');
            $selected = collect($this->measurementSettings)->filter(fn ($settings) => $settings['selected']);
            $allowed = MeasurementField::query()->where(fn ($query) => $query->active()->orWhereIn('id', $existing))->pluck('id');
            foreach ($selected->keys() as $id) {
                if (! ctype_digit((string) $id) || ! $allowed->containsStrict((int) $id)) {
                    throw ValidationException::withMessages(['measurementSettings' => __('Select active measurements or retain existing archived links.')]);
                }
            }
            $category->measurementFields()->sync($selected->mapWithKeys(fn ($settings, $id) => [
                $id => ['is_required' => $settings['required'], 'sort_order' => $settings['sort_order']],
            ])->all());
        });
        $this->managingMeasurements = false;
        session()->flash('success', __('Garment type measurements saved.'));
    }

    public function render()
    {
        $this->authorize('viewAny', GarmentCategory::class);
        $category = $this->garmentType()->loadCount(['measurementFields', 'optionGroups']);
        $items = $category->catalogItems()->with('branches:id,name')
            ->when(! auth()->user()->isGlobalAdmin(), fn ($query) => $query->availableForBranch((int) auth()->user()->branch_id));
        $category->setAttribute('catalog_items_count', auth()->user()->can('order_catalog.view') ? (clone $items)->count() : null);

        return view('livewire.garment-options.show', [
            'garmentType' => $category,
            'measurements' => $this->tab === 'measurements' ? $category->measurementFields()->get() : collect(),
            'groups' => $this->tab === 'customization' ? $category->optionGroups()->with('options')->orderBy('sort_order')->get() : collect(),
            'items' => $this->tab === 'items' && auth()->user()->can('order_catalog.view') ? $items->orderBy('name')->get() : collect(),
            'selectedFields' => $this->managingMeasurements ? MeasurementField::query()->whereKey(array_keys($this->measurementSettings))->orderBy('name')->get() : collect(),
            'availableFields' => $this->managingMeasurements ? MeasurementField::query()->active()
                ->where(fn ($query) => $query->where('name', 'like', '%'.$this->measurementSearch.'%')->orWhere('code', 'like', '%'.$this->measurementSearch.'%'))
                ->whereNotIn('id', collect($this->measurementSettings)->filter(fn ($settings) => $settings['selected'])->keys())
                ->orderBy('name')->limit(20)->get() : collect(),
        ])->title($category->name.' · '.__('Garment Type'));
    }

    private function garmentType(): GarmentCategory
    {
        return GarmentCategory::query()->findOrFail($this->garmentTypeId);
    }
}

<?php

namespace App\Livewire\OrderCatalog;

use App\Models\GarmentCategory;
use App\Models\MeasurementField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Measurement Definition')]
class MeasurementForm extends Component
{
    public ?int $measurementId = null;

    public string $name = '';

    public string $code = '';

    public string $defaultUnit = 'cm';

    public string $instructions = '';

    public bool $isGlobal = false;

    public bool $isActive = true;

    /** @var array<int, array{selected: bool, required: bool, sort_order: int}> */
    public array $categoryApplicability = [];

    public function mount(?MeasurementField $measurementField = null): void
    {
        $this->authorize('measurement_fields.manage');

        if ($measurementField?->exists) {
            $measurementField->load('garmentCategories');
            $this->measurementId = $measurementField->id;
            $this->name = $measurementField->name;
            $this->code = $measurementField->code;
            $this->defaultUnit = $measurementField->default_unit ?: $measurementField->unit;
            $this->instructions = (string) $measurementField->instructions;
            $this->isGlobal = $measurementField->is_global;
            $this->isActive = $measurementField->is_active;
        }

        $selected = $measurementField?->garmentCategories?->keyBy('id') ?? collect();
        foreach (GarmentCategory::query()->orderBy('sort_order')->orderBy('name')->get() as $category) {
            $existing = $selected->get($category->id);
            $this->categoryApplicability[$category->id] = [
                'selected' => $existing !== null,
                'required' => (bool) ($existing?->pivot?->is_required ?? false),
                'sort_order' => (int) ($existing?->pivot?->sort_order ?? $category->sort_order),
            ];
        }
    }

    public function updatedName(string $name): void
    {
        if ($this->measurementId === null && $this->code === '') {
            $this->code = $this->normalizeCode($name);
        }
    }

    public function save()
    {
        $this->authorize('measurement_fields.manage');
        $wasEditing = $this->measurementId !== null;
        $this->code = $this->normalizeCode($this->code);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('measurement_fields', 'code')->ignore($this->measurementId),
            ],
            'defaultUnit' => ['required', Rule::in(['cm', 'in', 'kg'])],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'isGlobal' => ['boolean'],
            'isActive' => ['boolean'],
            'categoryApplicability' => ['array'],
            'categoryApplicability.*.selected' => ['boolean'],
            'categoryApplicability.*.required' => ['boolean'],
            'categoryApplicability.*.sort_order' => ['integer', 'min:0', 'max:9999'],
        ], [
            'code.regex' => __('Use uppercase letters, numbers and underscores; the code must start with a letter.'),
        ]);

        DB::transaction(function () use ($validated): void {
            $field = $this->measurementId
                ? MeasurementField::query()->findOrFail($this->measurementId)
                : new MeasurementField;

            $field->fill([
                'name' => trim($validated['name']),
                'slug' => $field->exists ? $field->slug : Str::slug($validated['name']),
                'code' => $field->exists ? $field->code : $validated['code'],
                'default_unit' => $validated['defaultUnit'],
                'instructions' => filled($validated['instructions']) ? trim($validated['instructions']) : null,
                'is_global' => $validated['isGlobal'],
                'is_active' => $validated['isActive'],
                'garment_category_id' => $field->garment_category_id,
                'is_required' => $field->is_required ?? false,
                'sort_order' => $field->sort_order ?? 0,
            ])->save();

            $applicability = collect($validated['categoryApplicability'])
                ->filter(fn (array $settings): bool => (bool) $settings['selected'])
                ->mapWithKeys(fn (array $settings, int|string $categoryId): array => [
                    (int) $categoryId => [
                        'is_required' => (bool) $settings['required'],
                        'sort_order' => (int) $settings['sort_order'],
                    ],
                ])->all();

            $field->garmentCategories()->sync($applicability);
            $this->measurementId = $field->id;
        });

        session()->flash('success', $wasEditing ? __('Measurement definition saved.') : __('Measurement definition created.'));

        return $this->redirectRoute('order-catalog.index', ['tab' => 'measurements'], navigate: true);
    }

    public function render()
    {
        return view('livewire.order-catalog.measurement-form', [
            'categories' => GarmentCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    private function normalizeCode(string $value): string
    {
        $code = Str::upper(Str::snake(Str::lower(Str::ascii($value))));
        $code = preg_replace('/[^A-Z0-9_]+/', '_', $code) ?? '';

        return trim(preg_replace('/_+/', '_', $code) ?? '', '_');
    }
}

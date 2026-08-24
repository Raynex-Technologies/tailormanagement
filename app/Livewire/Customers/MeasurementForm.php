<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Models\MeasurementField;
use App\Services\Measurements\CustomerMeasurementProfileService;
use App\Support\Customers\CustomerAccess;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class MeasurementForm extends Component
{
    public Customer $customer;

    /** @var list<array{measurement_field_id: int, label: string, code: string, value: string, unit: string, archived: bool}> */
    public array $rows = [];

    /** @var list<int> */
    public array $initialFieldIds = [];

    public string $selectedMeasurementFieldId = '';

    public string $measuredAt = '';

    public string $notes = '';

    public bool $isUpdate = false;

    public function mount(Customer $customer, CustomerMeasurementProfileService $service): void
    {
        CustomerAccess::authorizeManage($customer);
        $this->customer = $customer->load('branch');
        $this->measuredAt = now()->toDateString();

        $current = $service->currentFor($customer);
        if (! $current) {
            return;
        }

        $this->isUpdate = true;
        $this->notes = (string) $current->notes;
        $this->rows = $current->orderedValues()
            ->map(fn ($value): array => [
                'measurement_field_id' => $value->measurement_field_id,
                'label' => $value->field?->name ?: ($value->field_label_snapshot ?: __('Archived measurement')),
                'code' => $value->field?->code ?: ($value->field_code_snapshot ?: '—'),
                'value' => (string) $value->value,
                'unit' => $value->unit,
                'archived' => ! ($value->field?->is_active ?? false),
            ])
            ->values()
            ->all();
        $this->initialFieldIds = collect($this->rows)->pluck('measurement_field_id')->all();
    }

    public function addMeasurement(): void
    {
        CustomerAccess::authorizeManage($this->customer);

        $fieldId = filter_var($this->selectedMeasurementFieldId, FILTER_VALIDATE_INT);
        if (! $fieldId) {
            $this->addError('selectedMeasurementFieldId', __('Select a measurement to add.'));

            return;
        }

        if (collect($this->rows)->contains('measurement_field_id', $fieldId)) {
            $this->addError('selectedMeasurementFieldId', __('That measurement is already included.'));

            return;
        }

        $field = MeasurementField::query()->active()->findOrFail($fieldId);
        $this->rows[] = [
            'measurement_field_id' => $field->id,
            'label' => $field->name,
            'code' => $field->code,
            'value' => '',
            'unit' => $field->default_unit ?: $field->unit,
            'archived' => false,
        ];
        $this->selectedMeasurementFieldId = '';
        $this->resetErrorBag('selectedMeasurementFieldId');
    }

    public function removeMeasurement(int $index): void
    {
        CustomerAccess::authorizeManage($this->customer);

        if (! array_key_exists($index, $this->rows)) {
            return;
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function save(CustomerMeasurementProfileService $service)
    {
        CustomerAccess::authorizeManage($this->customer);

        $validated = $this->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.measurement_field_id' => ['required', 'integer', 'distinct'],
            'rows.*.value' => ['required', 'string', 'regex:/^(?:0\.(?:0[1-9]|[1-9]\d?)|[1-9]\d{0,7}(?:\.\d{1,2})?)$/'],
            'rows.*.unit' => ['required', Rule::in(CustomerMeasurementProfileService::UNITS)],
            'measuredAt' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'rows.min' => __('Add at least one measurement.'),
            'rows.*.measurement_field_id.distinct' => __('Each measurement definition may only be used once.'),
            'rows.*.value.regex' => __('Enter a positive measurement with no more than two decimal places.'),
        ]);

        $submittedFieldIds = collect($validated['rows'])
            ->pluck('measurement_field_id')
            ->map(fn ($id): int => (int) $id);
        $removedFieldIds = collect($this->initialFieldIds)
            ->diff($submittedFieldIds)
            ->values()
            ->all();

        $service->createRevision(
            customer: $this->customer,
            changes: collect($validated['rows'])->map(fn (array $row): array => [
                'measurement_field_id' => (int) $row['measurement_field_id'],
                'value' => $row['value'],
                'unit' => $row['unit'],
            ])->values()->all(),
            removedFieldIds: $removedFieldIds,
            measuredAt: $validated['measuredAt'],
            recordedBy: auth()->user(),
            notes: $validated['notes'] ?: null,
        );

        session()->flash(
            'success',
            $this->isUpdate ? __('Customer measurements updated as a new revision.') : __('Customer measurements recorded.'),
        );

        return $this->redirectRoute('customers.show', $this->customer, navigate: true);
    }

    public function render()
    {
        $includedFieldIds = collect($this->rows)->pluck('measurement_field_id');

        return view('livewire.customers.measurement-form', [
            'availableFields' => MeasurementField::query()
                ->active()
                ->whereNotIn('id', $includedFieldIds)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'default_unit']),
        ])->title($this->isUpdate ? __('Update Customer Measurements') : __('Record Customer Measurements'));
    }
}

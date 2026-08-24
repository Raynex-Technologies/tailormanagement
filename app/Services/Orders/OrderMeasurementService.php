<?php

namespace App\Services\Orders;

use App\Enums\OrderCatalogItemType;
use App\Models\Customer;
use App\Models\MeasurementField;
use App\Models\MeasurementProfile;
use App\Models\OrderCatalogItem;
use App\Models\OrderLine;
use App\Models\OrderMeasurement;
use App\Support\Orders\OrderMeasurementSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class OrderMeasurementService
{
    /** @var list<string> */
    public const UNITS = ['cm', 'in', 'kg'];

    public function __construct(private readonly OrderMeasurementSnapshot $snapshot) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function initializeLineTemplates(array $lines): array
    {
        $catalogItemIds = collect($lines)
            ->pluck('order_catalog_item_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique();

        $catalogItems = OrderCatalogItem::query()
            ->whereIn('id', $catalogItemIds)
            ->with(['garmentCategory.measurementFields' => fn ($query) => $query->active()])
            ->get()
            ->keyBy('id');

        return collect($lines)
            ->map(fn (array $line): array => $this->initializeLineTemplate($line, $catalogItems))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function applyProfile(array $line, Customer $customer, int $profileId): array
    {
        $profile = MeasurementProfile::query()
            ->forCustomer($customer)
            ->where('profile_name', 'Default')
            ->with(['values.field'])
            ->findOrFail($profileId);

        $values = $profile->values->keyBy('measurement_field_id');
        $line['measurements'] = collect($line['measurements'] ?? [])
            ->map(function (array $row) use ($values): array {
                $fieldId = (int) ($row['measurement_field_id'] ?? 0);
                if ($fieldId <= 0 || ! $values->has($fieldId)) {
                    return $row;
                }

                $profileValue = $values->get($fieldId);
                $field = $profileValue->field;
                $row['code'] = $field?->is_active
                    ? $field->code
                    : ($row['code'] ?? $profileValue->field_code_snapshot);
                $row['label'] = $field?->is_active
                    ? $field->name
                    : ($row['label'] ?? $profileValue->field_label_snapshot);
                $row['value'] = (string) $profileValue->value;
                $row['unit'] = $profileValue->unit;
                $row['value_source'] = 'profile';

                return $row;
            })
            ->values()
            ->all();

        $line['measurement_source_profile_id'] = $profile->id;
        $line['measurement_source_lineage'] = $profile->lineage_uuid;
        $line['measurement_source_revision'] = $profile->revision;
        $line['measurement_source_name'] = $profile->profile_name;
        $line['measurement_source_measured_at'] = $profile->measured_at?->toDateString();
        $line['measurement_profile_selection'] = (string) $profile->id;
        $line['measurement_pending_profile_id'] = null;

        return $line;
    }

    public function hasReplaceableValues(array $line): bool
    {
        return collect($line['measurements'] ?? [])->contains(
            fn (array $row): bool => (int) ($row['measurement_field_id'] ?? 0) > 0
                && trim((string) ($row['value'] ?? '')) !== ''
        );
    }

    /** @return array<string, mixed> */
    public function enterManually(array $line): array
    {
        $line['measurements'] = collect($line['measurements'] ?? [])
            ->map(function (array $row): array {
                if (($row['value_source'] ?? null) === 'profile') {
                    $row['value_source'] = 'manual';
                }

                return $row;
            })
            ->values()
            ->all();
        $line['measurement_source_profile_id'] = null;
        $line['measurement_source_lineage'] = null;
        $line['measurement_source_revision'] = null;
        $line['measurement_source_name'] = null;
        $line['measurement_source_measured_at'] = null;
        $line['measurement_profile_selection'] = '';
        $line['measurement_pending_profile_id'] = null;

        return $line;
    }

    /** @return array<string, mixed> */
    public function clearUnsavedProfileValues(array $line): array
    {
        $line['measurements'] = collect($line['measurements'] ?? [])
            ->map(function (array $row): array {
                if (($row['value_source'] ?? null) === 'profile') {
                    $row['value'] = '';
                    $row['unit'] = $row['default_unit'] ?? $row['unit'] ?? 'cm';
                    $row['value_source'] = null;
                }

                return $row;
            })
            ->values()
            ->all();

        return $this->enterManually($line);
    }

    /** @return Collection<int, MeasurementProfile> */
    public function boundedProfileOptions(Customer $customer): Collection
    {
        return $customer->measurementProfiles()
            ->where('profile_name', 'Default')
            ->orderByDesc('revision')
            ->limit(8)
            ->get(['id', 'customer_id', 'revision', 'is_current', 'measured_at']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function activeFieldOptionsByLine(array $lines): array
    {
        if (! collect($lines)->contains(fn (array $line): bool => (bool) ($line['measurement_enabled'] ?? false))) {
            return [];
        }

        $fields = MeasurementField::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'default_unit', 'instructions', 'sort_order']);

        return collect($lines)->mapWithKeys(function (array $line, int $index) use ($fields): array {
            if (! ($line['measurement_enabled'] ?? false)) {
                return [$index => collect()];
            }

            $included = collect($line['measurements'] ?? [])
                ->pluck('measurement_field_id')
                ->filter()
                ->map(fn ($id): int => (int) $id);

            return [$index => $fields->reject(fn (MeasurementField $field): bool => $included->contains($field->id))->values()];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function prepareSnapshots(array $lines, ?Customer $customer): array
    {
        $fieldIds = collect($lines)
            ->flatMap(fn (array $line): array => collect($line['measurements'] ?? [])
                ->pluck('measurement_field_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->all())
            ->unique();
        $fields = MeasurementField::query()->whereIn('id', $fieldIds)->get()->keyBy('id');

        $lineIds = collect($lines)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->unique();
        $existingGroups = OrderMeasurement::withTrashed()
            ->whereIn('order_line_id', $lineIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('order_line_id');

        $sourceProfileIds = collect($lines)
            ->pluck('measurement_source_profile_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique();
        $sourceProfiles = $customer
            ? MeasurementProfile::query()
                ->forCustomer($customer)
                ->whereIn('id', $sourceProfileIds)
                ->get()
                ->keyBy('id')
            : collect();

        $prepared = [];
        foreach ($lines as $index => $line) {
            $existingRows = $existingGroups->get((int) ($line['id'] ?? 0), collect());
            $activeRows = $existingRows->whereNull('deleted_at');
            $existing = $activeRows->first();

            if ($existing && $this->snapshot->isUntouched($line)) {
                $prepared[$index] = ['action' => 'preserve'];

                continue;
            }

            if ($activeRows->count() > 1) {
                throw ValidationException::withMessages([
                    "lines.$index.measurements" => __('This legacy order line has multiple active measurement records and must be reviewed before editing measurements.'),
                ]);
            }

            $entries = $this->normalizedEntries($line, $fields, $existing, $index);
            if ($entries === []) {
                $prepared[$index] = ['action' => 'delete'];

                continue;
            }

            $provenance = $this->validatedProvenance($line, $customer, $sourceProfiles, $existing, $index);
            $prepared[$index] = [
                'action' => 'save',
                'measurements' => [
                    'version' => OrderMeasurementSnapshot::VERSION,
                    'entries' => $entries,
                ],
                'source_measurement_profile_id' => $provenance['profile_id'],
                'source_profile_lineage' => $provenance['lineage'],
                'source_profile_revision' => $provenance['revision'],
                'source_profile_name' => $provenance['name'],
                'source_measured_at' => $provenance['measured_at'],
                'snapshot_format_version' => OrderMeasurementSnapshot::VERSION,
            ];
        }

        return $prepared;
    }

    public function persistPrepared(OrderLine $line, array $prepared): void
    {
        if (($prepared['action'] ?? null) === 'preserve') {
            return;
        }

        $activeRows = OrderMeasurement::query()->where('order_line_id', $line->id)->latest('id')->get();
        if ($activeRows->count() > 1) {
            throw ValidationException::withMessages([
                'measurements' => __('This order line has multiple active measurement records and cannot be changed automatically.'),
            ]);
        }

        $measurement = $activeRows->first();
        if (($prepared['action'] ?? null) === 'delete') {
            $measurement?->delete();

            return;
        }

        if (! $measurement) {
            $measurement = OrderMeasurement::onlyTrashed()
                ->where('order_line_id', $line->id)
                ->latest('id')
                ->first();

            if ($measurement) {
                $measurement->restore();
            } else {
                $measurement = new OrderMeasurement(['order_line_id' => $line->id]);
            }
        }

        $measurement->fill(collect($prepared)->except('action')->all())->save();
    }

    private function initializeLineTemplate(array $line, Collection $catalogItems): array
    {
        $state = array_merge($this->snapshot->emptyEditorState(), $line);
        $catalogItem = $catalogItems->get((int) ($line['order_catalog_item_id'] ?? 0));
        $isManualLine = ! $catalogItem && empty($line['inventory_item_id']);
        $enabled = $isManualLine || (bool) ($line['requires_measurements'] ?? $catalogItem?->requires_measurements ?? false);
        $category = $catalogItem?->type === OrderCatalogItemType::Garment ? $catalogItem->garmentCategory : null;

        $state['measurement_enabled'] = $enabled;
        $state['garment_category_id'] = $category?->id;
        $state['catalog_item_type'] = $catalogItem?->type?->value;
        $state['measurement_template_configured'] = $enabled && $category !== null;
        $state['measurement_field_selection'] = $state['measurement_field_selection'] ?? '';

        if (! $enabled || ! $category) {
            $state['measurements'] = $this->normalizeIncomingRows($state['measurements'] ?? []);

            return $state;
        }

        $rows = collect($this->normalizeIncomingRows($state['measurements'] ?? []));
        $fields = $category->measurementFields
            ->sortBy(fn (MeasurementField $field): string => sprintf(
                '%d|%010d|%010d|%s|%s',
                $field->pivot->is_required ? 0 : 1,
                $field->pivot->sort_order,
                $field->sort_order,
                $field->name,
                $field->code,
            ));

        foreach ($fields as $field) {
            $existingIndex = $rows->search(fn (array $row): bool => (int) ($row['measurement_field_id'] ?? 0) === $field->id);
            if ($existingIndex !== false) {
                $row = $rows->get($existingIndex);
                $row['required'] = (bool) $field->pivot->is_required;
                $row['expected'] = true;
                $row['instructions'] = $field->instructions;
                $row['default_unit'] = $field->default_unit;
                $row['template_sort_order'] = (int) $field->pivot->sort_order;
                $rows->put($existingIndex, $row);

                continue;
            }

            $rows->push($this->canonicalRow(
                field: $field,
                required: (bool) $field->pivot->is_required,
                expected: true,
                templateSortOrder: (int) $field->pivot->sort_order,
            ));
        }

        $state['measurements'] = $rows
            ->sortBy(fn (array $row): string => sprintf(
                '%d|%d|%010d|%s|%s',
                ($row['expected'] ?? false) ? 0 : 1,
                ($row['required'] ?? false) ? 0 : 1,
                $row['template_sort_order'] ?? PHP_INT_MAX,
                $row['label'] ?? '',
                $row['code'] ?? '',
            ))
            ->values()
            ->all();

        return $state;
    }

    private function normalizeIncomingRows(array $rows): array
    {
        return collect($rows)
            ->map(function (array $row): array {
                if (array_key_exists('key', $row)) {
                    return [
                        'measurement_field_id' => null,
                        'code' => null,
                        'label' => (string) ($row['key'] ?? ''),
                        'value' => (string) ($row['value'] ?? ''),
                        'unit' => (string) ($row['unit'] ?? 'cm'),
                        'required' => false,
                        'expected' => false,
                        'is_custom' => true,
                        'instructions' => null,
                        'default_unit' => (string) ($row['unit'] ?? 'cm'),
                        'from_snapshot' => false,
                        'value_source' => null,
                    ];
                }

                return $row;
            })
            ->filter(fn (array $row): bool => trim((string) ($row['label'] ?? '')) !== ''
                || trim((string) ($row['value'] ?? '')) !== ''
                || (int) ($row['measurement_field_id'] ?? 0) > 0)
            ->values()
            ->all();
    }

    private function canonicalRow(MeasurementField $field, bool $required = false, bool $expected = false, int $templateSortOrder = 0): array
    {
        return [
            'measurement_field_id' => $field->id,
            'code' => $field->code,
            'label' => $field->name,
            'value' => '',
            'unit' => $field->default_unit,
            'required' => $required,
            'expected' => $expected,
            'is_custom' => false,
            'instructions' => $field->instructions,
            'default_unit' => $field->default_unit,
            'template_sort_order' => $templateSortOrder,
            'from_snapshot' => false,
            'value_source' => null,
        ];
    }

    private function normalizedEntries(array $line, Collection $fields, ?OrderMeasurement $existing, int $lineIndex): array
    {
        $existingEntries = collect($this->snapshot->present($existing)['entries'])->keyBy('measurement_field_id');
        $entries = [];
        $seenFieldIds = [];

        foreach ($line['measurements'] ?? [] as $rowIndex => $rawRow) {
            $row = array_key_exists('key', $rawRow)
                ? ['is_custom' => true, 'label' => $rawRow['key'] ?? '', 'value' => $rawRow['value'] ?? '', 'unit' => $rawRow['unit'] ?? 'cm']
                : $rawRow;
            $isCustom = (bool) ($row['is_custom'] ?? false);
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            $unit = trim((string) ($row['unit'] ?? ''));

            if ($isCustom) {
                if ($label === '' && $value === '') {
                    continue;
                }
                $this->validateCustomEntry($label, $value, $unit, $lineIndex, $rowIndex);
                $entries[] = [
                    'measurement_field_id' => null,
                    'code' => null,
                    'label' => $label,
                    'value' => $value,
                    'unit' => $unit,
                    'is_custom' => true,
                ];

                continue;
            }

            $fieldId = (int) ($row['measurement_field_id'] ?? 0);
            if ($fieldId <= 0) {
                continue;
            }
            if (isset($seenFieldIds[$fieldId])) {
                throw ValidationException::withMessages([
                    "lines.$lineIndex.measurements.$rowIndex.measurement_field_id" => __('Each canonical measurement may only be used once per garment.'),
                ]);
            }
            $seenFieldIds[$fieldId] = true;

            $field = $fields->get($fieldId);
            if (! $field) {
                throw ValidationException::withMessages([
                    "lines.$lineIndex.measurements.$rowIndex.measurement_field_id" => __('The selected measurement definition no longer exists.'),
                ]);
            }
            if (! $field->is_active && ! $existingEntries->has($fieldId)) {
                throw ValidationException::withMessages([
                    "lines.$lineIndex.measurements.$rowIndex.measurement_field_id" => __('Archived measurement definitions cannot be newly added.'),
                ]);
            }
            if ($value === '') {
                continue;
            }
            $this->validateValueAndUnit($value, $unit, $lineIndex, $rowIndex);
            $historical = $existingEntries->get($fieldId);
            $entries[] = [
                'measurement_field_id' => $fieldId,
                'code' => $field->is_active ? $field->code : ($historical['code'] ?? $row['code'] ?? null),
                'label' => $field->is_active ? $field->name : ($historical['label'] ?? $row['label'] ?? __('Archived measurement')),
                'value' => $value,
                'unit' => $unit,
                'is_custom' => false,
            ];
        }

        return $entries;
    }

    private function validateCustomEntry(string $label, string $value, string $unit, int $lineIndex, int $rowIndex): void
    {
        if ($label === '' || mb_strlen($label) > 120) {
            throw ValidationException::withMessages([
                "lines.$lineIndex.measurements.$rowIndex.label" => __('Custom measurements require a label of 120 characters or fewer.'),
            ]);
        }
        if (preg_match('/\b(?:slim|regular|loose)\s+fit\b|\b(?:peak|notch|shawl)\s+lapel\b/i', $label)) {
            throw ValidationException::withMessages([
                "lines.$lineIndex.measurements.$rowIndex.label" => __('Fit and lapel choices belong to garment options, not measurements.'),
            ]);
        }
        $this->validateValueAndUnit($value, $unit, $lineIndex, $rowIndex);
    }

    private function validateValueAndUnit(string $value, string $unit, int $lineIndex, int $rowIndex): void
    {
        if (! preg_match('/^(?:0\.(?:0[1-9]|[1-9]\d?)|[1-9]\d{0,7}(?:\.\d{1,2})?)$/', $value)) {
            throw ValidationException::withMessages([
                "lines.$lineIndex.measurements.$rowIndex.value" => __('Enter a positive measurement with no more than two decimal places.'),
            ]);
        }
        if (! in_array($unit, self::UNITS, true)) {
            throw ValidationException::withMessages([
                "lines.$lineIndex.measurements.$rowIndex.unit" => __('Select cm, in, or kg.'),
            ]);
        }
    }

    private function validatedProvenance(
        array $line,
        ?Customer $customer,
        Collection $sourceProfiles,
        ?OrderMeasurement $existing,
        int $lineIndex,
    ): array {
        $profileId = (int) ($line['measurement_source_profile_id'] ?? 0);
        if ($profileId > 0) {
            $profile = $sourceProfiles->get($profileId);
            if (! $customer || ! $profile) {
                throw ValidationException::withMessages([
                    "lines.$lineIndex.measurement_source_profile_id" => __('The selected measurement profile does not belong to the selected customer.'),
                ]);
            }

            return [
                'profile_id' => $profile->id,
                'lineage' => $profile->lineage_uuid,
                'revision' => $profile->revision,
                'name' => $profile->profile_name,
                'measured_at' => $profile->measured_at,
            ];
        }

        if ($existing && ($line['measurement_source_lineage'] ?? null)) {
            return [
                'profile_id' => null,
                'lineage' => $existing->source_profile_lineage,
                'revision' => $existing->source_profile_revision,
                'name' => $existing->source_profile_name,
                'measured_at' => $existing->source_measured_at,
            ];
        }

        return [
            'profile_id' => null,
            'lineage' => null,
            'revision' => null,
            'name' => null,
            'measured_at' => null,
        ];
    }
}

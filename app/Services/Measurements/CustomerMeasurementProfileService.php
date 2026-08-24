<?php

namespace App\Services\Measurements;

use App\Models\Customer;
use App\Models\MeasurementField;
use App\Models\MeasurementProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomerMeasurementProfileService
{
    public const PROFILE_NAME = 'Default';

    /** @var list<string> */
    public const UNITS = ['cm', 'in', 'kg'];

    public function currentFor(Customer $customer): ?MeasurementProfile
    {
        return $customer->currentMeasurementProfile()
            ->with(['values.field', 'recordedBy'])
            ->first();
    }

    /**
     * @param  list<array{measurement_field_id: int|string, value: string, unit: string}>  $changes
     * @param  list<int|string>  $removedFieldIds
     */
    public function createRevision(
        Customer $customer,
        array $changes,
        array $removedFieldIds,
        CarbonInterface|string $measuredAt,
        ?User $recordedBy,
        ?string $notes = null,
    ): MeasurementProfile {
        $measurementDate = $this->validatedMeasurementDate($measuredAt);
        $normalizedChanges = $this->normalizeChanges($changes);
        $normalizedRemovals = $this->normalizeRemovals($removedFieldIds);

        return DB::transaction(function () use (
            $customer,
            $normalizedChanges,
            $normalizedRemovals,
            $measurementDate,
            $recordedBy,
            $notes,
        ): MeasurementProfile {
            $lockedCustomer = Customer::withoutGlobalScopes()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $current = MeasurementProfile::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('profile_name', self::PROFILE_NAME)
                ->where('is_current', true)
                ->with(['values.field'])
                ->lockForUpdate()
                ->first();

            $currentValues = $current
                ? $current->values->keyBy('measurement_field_id')
                : collect();

            $this->ensureRemovalsBelongToCurrent($normalizedRemovals, $currentValues);

            $requestedFieldIds = collect($normalizedChanges)->pluck('measurement_field_id');
            $fields = MeasurementField::query()
                ->whereIn('id', $requestedFieldIds)
                ->get()
                ->keyBy('id');

            $this->ensureRequestedFieldsExist($requestedFieldIds, $fields);
            $this->ensureNewFieldsAreActive($requestedFieldIds, $currentValues, $fields);

            $nextValues = $this->copyForwardValues($currentValues, $normalizedRemovals);
            foreach ($normalizedChanges as $change) {
                $fieldId = $change['measurement_field_id'];
                $field = $fields->get($fieldId);
                $existing = $currentValues->get($fieldId);

                $nextValues->put($fieldId, [
                    'measurement_field_id' => $fieldId,
                    'field_code_snapshot' => $field->is_active
                        ? $field->code
                        : ($existing?->field_code_snapshot ?: $field->code),
                    'field_label_snapshot' => $field->is_active
                        ? $field->name
                        : ($existing?->field_label_snapshot ?: $field->name),
                    'value' => $change['value'],
                    'unit' => $change['unit'],
                    'note' => $existing?->note,
                ]);
            }

            if ($nextValues->isEmpty()) {
                throw ValidationException::withMessages([
                    'rows' => __('At least one measurement is required.'),
                ]);
            }

            $lineage = $current?->lineage_uuid ?: (string) Str::uuid();
            $revision = $current ? $current->revision + 1 : 1;

            if ($current) {
                MeasurementProfile::query()->whereKey($current->id)->update([
                    'is_current' => false,
                    'current_lineage_key' => null,
                    'current_customer_profile_key' => null,
                ]);
            }

            $profile = MeasurementProfile::query()->create([
                'customer_id' => $lockedCustomer->id,
                'lineage_uuid' => $lineage,
                'revision' => $revision,
                'is_current' => true,
                'current_lineage_key' => $lineage,
                'current_customer_profile_key' => self::customerProfileKey($lockedCustomer->id),
                'profile_name' => self::PROFILE_NAME,
                'measured_at' => $measurementDate,
                'recorded_by_user_id' => $recordedBy?->id,
                'notes' => filled($notes) ? trim((string) $notes) : null,
            ]);

            $profile->values()->createMany($nextValues->values()->all());

            return $profile->load(['values.field', 'recordedBy']);
        }, 3);
    }

    public static function customerProfileKey(int $customerId): string
    {
        return $customerId.':'.hash('sha256', Str::lower(self::PROFILE_NAME));
    }

    /**
     * @param  list<array{measurement_field_id: int|string, value: string, unit: string}>  $changes
     * @return list<array{measurement_field_id: int, value: string, unit: string}>
     */
    private function normalizeChanges(array $changes): array
    {
        $normalized = [];
        $seen = [];

        foreach ($changes as $index => $change) {
            if (! is_array($change)) {
                throw ValidationException::withMessages([
                    "rows.$index" => __('The measurement row is invalid.'),
                ]);
            }

            $fieldId = filter_var($change['measurement_field_id'] ?? null, FILTER_VALIDATE_INT);
            $value = trim((string) ($change['value'] ?? ''));
            $unit = trim((string) ($change['unit'] ?? ''));

            if (! $fieldId || isset($seen[$fieldId])) {
                throw ValidationException::withMessages([
                    "rows.$index.measurement_field_id" => __('Each measurement definition may only be used once.'),
                ]);
            }

            if (! preg_match('/^(?:0\.(?:0[1-9]|[1-9]\d?)|[1-9]\d{0,7}(?:\.\d{1,2})?)$/', $value)) {
                throw ValidationException::withMessages([
                    "rows.$index.value" => __('Enter a positive measurement with no more than two decimal places.'),
                ]);
            }

            if (! in_array($unit, self::UNITS, true)) {
                throw ValidationException::withMessages([
                    "rows.$index.unit" => __('Select a supported unit.'),
                ]);
            }

            $seen[$fieldId] = true;
            $normalized[] = [
                'measurement_field_id' => $fieldId,
                'value' => $value,
                'unit' => $unit,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<int|string>  $removedFieldIds
     * @return list<int>
     */
    private function normalizeRemovals(array $removedFieldIds): array
    {
        $normalized = [];
        foreach ($removedFieldIds as $fieldId) {
            $validated = filter_var($fieldId, FILTER_VALIDATE_INT);
            if (! $validated || in_array($validated, $normalized, true)) {
                throw ValidationException::withMessages([
                    'rows' => __('The removed measurement list is invalid.'),
                ]);
            }
            $normalized[] = $validated;
        }

        return $normalized;
    }

    private function validatedMeasurementDate(CarbonInterface|string $measuredAt): CarbonImmutable
    {
        try {
            $date = $measuredAt instanceof CarbonInterface
                ? CarbonImmutable::instance($measuredAt)
                : CarbonImmutable::parse($measuredAt);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'measuredAt' => __('Enter a valid measurement date.'),
            ]);
        }

        if ($date->startOfDay()->isAfter(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'measuredAt' => __('The measurement date cannot be in the future.'),
            ]);
        }

        return $date->startOfDay();
    }

    private function ensureRemovalsBelongToCurrent(array $removals, Collection $currentValues): void
    {
        foreach ($removals as $fieldId) {
            if (! $currentValues->has($fieldId)) {
                throw ValidationException::withMessages([
                    'rows' => __('Only measurements from the current revision can be removed.'),
                ]);
            }
        }
    }

    private function ensureRequestedFieldsExist(Collection $requestedFieldIds, Collection $fields): void
    {
        if ($requestedFieldIds->unique()->count() !== $fields->count()) {
            throw ValidationException::withMessages([
                'rows' => __('A selected measurement definition no longer exists.'),
            ]);
        }
    }

    private function ensureNewFieldsAreActive(Collection $requestedFieldIds, Collection $currentValues, Collection $fields): void
    {
        foreach ($requestedFieldIds as $fieldId) {
            if (! $currentValues->has($fieldId) && ! $fields->get($fieldId)->is_active) {
                throw ValidationException::withMessages([
                    'rows' => __('Archived measurement definitions cannot be added to a new revision.'),
                ]);
            }
        }
    }

    private function copyForwardValues(Collection $currentValues, array $removals): Collection
    {
        return $currentValues
            ->reject(fn ($value): bool => in_array($value->measurement_field_id, $removals, true))
            ->mapWithKeys(function ($value): array {
                $field = $value->field;

                return [
                    $value->measurement_field_id => [
                        'measurement_field_id' => $value->measurement_field_id,
                        'field_code_snapshot' => $field?->is_active
                            ? $field->code
                            : ($value->field_code_snapshot ?: $field?->code),
                        'field_label_snapshot' => $field?->is_active
                            ? $field->name
                            : ($value->field_label_snapshot ?: $field?->name),
                        'value' => $value->value,
                        'unit' => $value->unit,
                        'note' => $value->note,
                    ],
                ];
            });
    }
}

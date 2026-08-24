<?php

namespace App\Services\Orders;

use App\Models\Customer;
use App\Models\OrderMeasurement;
use App\Services\Measurements\CustomerMeasurementProfileService;
use App\Support\Orders\OrderMeasurementSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class OrderMeasurementSavebackService
{
    public function __construct(
        private readonly CustomerMeasurementProfileService $profiles,
        private readonly OrderMeasurementSnapshot $snapshots,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, mixed>|null
     */
    public function proposal(Customer $customer, array $lines, bool $isEdit): ?array
    {
        $current = $this->profiles->currentFor($customer);
        $currentValues = $current?->values->keyBy('measurement_field_id') ?? collect();
        $originalByLine = $isEdit ? $this->originalCanonicalValues($lines) : collect();
        $occurrences = collect();

        foreach ($lines as $lineIndex => $line) {
            foreach ($line['measurements'] ?? [] as $row) {
                $fieldId = (int) ($row['measurement_field_id'] ?? 0);
                $value = trim((string) ($row['value'] ?? ''));
                $unit = trim((string) ($row['unit'] ?? ''));
                if ($fieldId <= 0 || $value === '' || ! in_array($unit, CustomerMeasurementProfileService::UNITS, true)) {
                    continue;
                }

                if ($isEdit && filled($line['id'] ?? null)) {
                    $original = $originalByLine->get((int) $line['id'], collect())->get($fieldId);
                    if ($original && $this->sameValue($value, $unit, $original['value'], $original['unit'])) {
                        continue;
                    }
                }

                $occurrences->push([
                    'field_id' => $fieldId,
                    'label' => (string) ($row['label'] ?? __('Measurement')),
                    'value' => $value,
                    'unit' => $unit,
                    'line_index' => $lineIndex,
                    'line_label' => (string) (($line['item_name'] ?? '') ?: __('Order item :number', ['number' => $lineIndex + 1])),
                ]);
            }
        }

        $changes = [];
        $conflicts = [];
        foreach ($occurrences->groupBy('field_id') as $fieldId => $fieldOccurrences) {
            $unique = $fieldOccurrences->unique(fn (array $item): string => $this->comparisonKey($item['value'], $item['unit']))->values();
            $currentValue = $currentValues->get((int) $fieldId);

            if ($unique->count() === 1) {
                $candidate = $unique->first();
                if ($currentValue && $this->sameValue($candidate['value'], $candidate['unit'], (string) $currentValue->value, $currentValue->unit)) {
                    continue;
                }

                $changes[] = $this->presentCandidate($candidate, $currentValue);

                continue;
            }

            $options = $unique->map(function (array $candidate, int $index): array {
                return [
                    ...$this->presentCandidate($candidate, null),
                    'key' => 'option:'.$index,
                ];
            })->all();

            $conflicts[] = [
                'field_id' => (int) $fieldId,
                'label' => (string) ($unique->first()['label'] ?? __('Measurement')),
                'current' => $currentValue ? [
                    'value' => (string) $currentValue->value,
                    'unit' => $currentValue->unit,
                ] : null,
                'options' => $options,
            ];
        }

        if ($changes === [] && $conflicts === []) {
            return null;
        }

        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'current_profile_id' => $current?->id,
            'current_revision' => $current?->revision,
            'has_current' => $current !== null,
            'changes' => $changes,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @param  array<int|string, string>  $choices
     * @return list<array{measurement_field_id: int, value: string, unit: string}>
     */
    public function resolvedChanges(array $proposal, array $choices): array
    {
        $resolved = collect($proposal['changes'] ?? [])->map(fn (array $change): array => [
            'measurement_field_id' => (int) $change['field_id'],
            'value' => (string) $change['value'],
            'unit' => (string) $change['unit'],
        ]);

        foreach ($proposal['conflicts'] ?? [] as $conflict) {
            $fieldId = (int) ($conflict['field_id'] ?? 0);
            $choice = (string) ($choices[$fieldId] ?? '');
            if ($choice === 'keep') {
                continue;
            }

            $selected = collect($conflict['options'] ?? [])->firstWhere('key', $choice);
            if (! $selected) {
                throw ValidationException::withMessages([
                    "customerMeasurementConflictChoices.$fieldId" => __('Choose which value, or the existing customer value, should be reusable.'),
                ]);
            }

            $resolved->push([
                'measurement_field_id' => $fieldId,
                'value' => (string) $selected['value'],
                'unit' => (string) $selected['unit'],
            ]);
        }

        return $resolved->values()->all();
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function originalCanonicalValues(array $lines): Collection
    {
        $lineIds = collect($lines)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->unique();

        return OrderMeasurement::query()
            ->whereIn('order_line_id', $lineIds)
            ->get()
            ->mapWithKeys(function (OrderMeasurement $measurement): array {
                $values = collect($this->snapshots->present($measurement)['entries'])
                    ->filter(fn (array $entry): bool => ! ($entry['is_custom'] ?? false) && filled($entry['measurement_field_id'] ?? null))
                    ->keyBy(fn (array $entry): int => (int) $entry['measurement_field_id']);

                return [$measurement->order_line_id => $values];
            });
    }

    /** @return array<string, mixed> */
    private function presentCandidate(array $candidate, mixed $current): array
    {
        return [
            'field_id' => (int) $candidate['field_id'],
            'label' => $candidate['label'],
            'value' => $candidate['value'],
            'unit' => $candidate['unit'],
            'line_index' => $candidate['line_index'],
            'line_label' => $candidate['line_label'],
            'current' => $current ? [
                'value' => (string) $current->value,
                'unit' => $current->unit,
            ] : null,
        ];
    }

    private function sameValue(string $leftValue, string $leftUnit, string $rightValue, string $rightUnit): bool
    {
        return $this->comparisonKey($leftValue, $leftUnit) === $this->comparisonKey($rightValue, $rightUnit);
    }

    private function comparisonKey(string $value, string $unit): string
    {
        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' || str_starts_with($normalized, '.') ? '0'.$normalized : $normalized;
        $normalized = str_contains($normalized, '.') ? rtrim(rtrim($normalized, '0'), '.') : $normalized;

        return ($normalized === '' ? '0' : $normalized).'|'.$unit;
    }
}

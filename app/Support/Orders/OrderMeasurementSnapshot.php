<?php

namespace App\Support\Orders;

use App\Models\OrderMeasurement;
use Illuminate\Support\Str;

final class OrderMeasurementSnapshot
{
    public const VERSION = 2;

    /** @return array<string, mixed> */
    public function editorState(?OrderMeasurement $measurement): array
    {
        if (! $measurement) {
            return $this->emptyEditorState();
        }

        $structured = $this->isStructured($measurement->measurements);
        $rows = $structured
            ? $this->structuredEditorRows($measurement->measurements['entries'])
            : $this->legacyEditorRows($measurement->measurements ?? []);

        $state = [
            'measurement_format' => $structured ? 'structured' : 'legacy',
            'measurements' => $rows,
            'measurement_source_profile_id' => $measurement->source_measurement_profile_id,
            'measurement_source_lineage' => $measurement->source_profile_lineage,
            'measurement_source_revision' => $measurement->source_profile_revision,
            'measurement_source_name' => $measurement->source_profile_name,
            'measurement_source_measured_at' => $measurement->source_measured_at?->toDateString(),
            'measurement_profile_selection' => '',
            'measurement_pending_profile_id' => null,
        ];
        $state['measurement_original_signature'] = $this->signature($state);

        return $state;
    }

    /** @return array<string, mixed> */
    public function emptyEditorState(): array
    {
        return [
            'measurement_format' => 'none',
            'measurements' => [],
            'measurement_source_profile_id' => null,
            'measurement_source_lineage' => null,
            'measurement_source_revision' => null,
            'measurement_source_name' => null,
            'measurement_source_measured_at' => null,
            'measurement_profile_selection' => '',
            'measurement_pending_profile_id' => null,
            'measurement_original_signature' => null,
        ];
    }

    /** @return array{version: int, entries: array<int, array<string, mixed>>, source: array<string, mixed>|null} */
    public function present(?OrderMeasurement $measurement): array
    {
        if (! $measurement) {
            return ['version' => 0, 'entries' => [], 'source' => null];
        }

        if ($this->isStructured($measurement->measurements)) {
            $entries = collect($measurement->measurements['entries'])
                ->map(fn (array $entry): array => [
                    'measurement_field_id' => $entry['measurement_field_id'] ?? null,
                    'code' => $entry['code'] ?? null,
                    'label' => (string) ($entry['label'] ?? __('Measurement')),
                    'value' => (string) ($entry['value'] ?? ''),
                    'unit' => $entry['unit'] ?? null,
                    'is_custom' => (bool) ($entry['is_custom'] ?? false),
                ])
                ->values()
                ->all();
        } else {
            $entries = collect($measurement->measurements ?? [])
                ->map(fn ($value, $label): array => [
                    'measurement_field_id' => null,
                    'code' => null,
                    'label' => (string) $label,
                    'value' => (string) $value,
                    'unit' => null,
                    'is_custom' => true,
                ])
                ->values()
                ->all();
        }

        $source = $measurement->source_profile_lineage || $measurement->source_profile_revision
            ? [
                'profile_id' => $measurement->source_measurement_profile_id,
                'lineage' => $measurement->source_profile_lineage,
                'revision' => $measurement->source_profile_revision,
                'name' => $measurement->source_profile_name,
                'measured_at' => $measurement->source_measured_at,
            ]
            : null;

        return [
            'version' => $this->isStructured($measurement->measurements) ? self::VERSION : 1,
            'entries' => $entries,
            'source' => $source,
        ];
    }

    public function isUntouched(array $state): bool
    {
        $original = $state['measurement_original_signature'] ?? null;

        return is_string($original)
            && $original !== ''
            && hash_equals($original, $this->signature($state));
    }

    public function signature(array $state): string
    {
        $rows = collect($state['measurements'] ?? [])
            ->map(function (array $row): array {
                $isCustom = (bool) ($row['is_custom'] ?? isset($row['key']));

                return [
                    'measurement_field_id' => $isCustom ? null : (int) ($row['measurement_field_id'] ?? 0),
                    'code' => $isCustom ? null : ($row['code'] ?? null),
                    'label' => trim((string) ($row['label'] ?? $row['key'] ?? '')),
                    'value' => trim((string) ($row['value'] ?? '')),
                    'unit' => $row['unit'] ?? null,
                    'is_custom' => $isCustom,
                ];
            })
            ->filter(fn (array $row): bool => $row['label'] !== '' || $row['value'] !== '')
            ->values()
            ->all();

        $payload = [
            'rows' => $rows,
            'source_profile_id' => $state['measurement_source_profile_id'] ?? null,
            'source_lineage' => $state['measurement_source_lineage'] ?? null,
            'source_revision' => $state['measurement_source_revision'] ?? null,
            'source_name' => $state['measurement_source_name'] ?? null,
            'source_measured_at' => $state['measurement_source_measured_at'] ?? null,
        ];

        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function isStructured(?array $measurements): bool
    {
        return ($measurements['version'] ?? null) === self::VERSION
            && is_array($measurements['entries'] ?? null);
    }

    /** @return array<int, array<string, mixed>> */
    private function structuredEditorRows(array $entries): array
    {
        return collect($entries)
            ->filter(fn ($entry): bool => is_array($entry))
            ->map(fn (array $entry): array => [
                'measurement_field_id' => $entry['measurement_field_id'] ?? null,
                'code' => $entry['code'] ?? null,
                'label' => (string) ($entry['label'] ?? ''),
                'value' => (string) ($entry['value'] ?? ''),
                'unit' => (string) ($entry['unit'] ?? 'cm'),
                'required' => false,
                'expected' => false,
                'is_custom' => (bool) ($entry['is_custom'] ?? false),
                'instructions' => null,
                'from_snapshot' => true,
                'value_source' => null,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyEditorRows(array $measurements): array
    {
        return collect($measurements)
            ->map(function ($rawValue, $label): array {
                [$value, $unit] = $this->parseLegacyValue((string) $rawValue);

                return [
                    'measurement_field_id' => null,
                    'code' => null,
                    'label' => (string) $label,
                    'value' => $value,
                    'unit' => $unit,
                    'required' => false,
                    'expected' => false,
                    'is_custom' => true,
                    'instructions' => null,
                    'from_snapshot' => true,
                    'value_source' => null,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array{0: string, 1: string} */
    private function parseLegacyValue(string $rawValue): array
    {
        if (preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)\s*(cm|in|kg)?\s*$/i', $rawValue, $matches)) {
            return [$matches[1], Str::lower($matches[2] ?? 'cm')];
        }

        return [trim($rawValue), 'cm'];
    }
}

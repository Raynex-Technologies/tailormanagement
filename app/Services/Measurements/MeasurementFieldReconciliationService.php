<?php

namespace App\Services\Measurements;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MeasurementFieldReconciliationService
{
    /**
     * Reconcile legacy category-owned fields into canonical definitions.
     *
     * Exact normalized name + slug + unit matches are safe to consolidate for
     * future use. Legacy rows remain in place (and are archived) so existing
     * foreign-key references are never destroyed.
     *
     * @return array{canonicalized: array<int, array<string, mixed>>, ambiguous: array<int, array<string, mixed>>}
     */
    public function reconcile(): array
    {
        $fields = DB::table('measurement_fields')->orderBy('id')->get();
        $categories = DB::table('garment_categories')->pluck('slug', 'id');

        foreach ($fields as $field) {
            DB::table('measurement_fields')->where('id', $field->id)->update([
                'default_unit' => $this->normalizedUnit($field->default_unit ?? $field->unit ?? 'cm'),
                'is_global' => filled($field->code ?? null)
                    ? (bool) $field->is_global
                    : $field->garment_category_id === null,
            ]);
        }

        $fields = DB::table('measurement_fields')->orderBy('id')->get();
        $safeGroups = $fields->groupBy(fn ($field): string => implode('|', [
            $this->normalizedName($field->name),
            Str::lower((string) $field->slug),
            $this->normalizedUnit($field->default_unit ?? $field->unit ?? 'cm'),
        ]));

        $report = ['canonicalized' => [], 'ambiguous' => []];

        foreach ($safeGroups as $group) {
            $canonical = $group->first();
            $canonicalCode = $this->uniqueCode($this->preferredCode($canonical), (int) $canonical->id);

            DB::table('measurement_fields')->where('id', $canonical->id)->update([
                'code' => $canonicalCode,
                'is_global' => $group->contains(fn ($field): bool => (bool) $field->is_global),
            ]);

            foreach ($group as $field) {
                if ($field->garment_category_id !== null) {
                    $this->upsertApplicability(
                        (int) $field->garment_category_id,
                        (int) $canonical->id,
                        (bool) $field->is_required,
                        (int) $field->sort_order,
                    );
                }

                if ((int) $field->id === (int) $canonical->id) {
                    continue;
                }

                DB::table('measurement_fields')->where('id', $field->id)->update([
                    'code' => $this->uniqueCode($canonicalCode.'_LEGACY_'.$field->id, (int) $field->id),
                    'is_active' => false,
                    'is_global' => false,
                ]);
            }

            if ($group->count() > 1) {
                $report['canonicalized'][] = [
                    'canonical_id' => (int) $canonical->id,
                    'code' => $canonicalCode,
                    'legacy_ids' => $group->skip(1)->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                ];
            }
        }

        $refreshed = DB::table('measurement_fields')->orderBy('id')->get();
        $ambiguousGroups = $refreshed->groupBy(fn ($field): string => $this->normalizedName($field->name));

        foreach ($ambiguousGroups as $normalizedName => $group) {
            $semanticVariants = $group->map(fn ($field): string => implode('|', [
                Str::lower((string) $field->slug),
                $this->normalizedUnit($field->default_unit ?? $field->unit ?? 'cm'),
            ]))->unique();

            if ($semanticVariants->count() < 2) {
                continue;
            }

            foreach ($group as $field) {
                if (filled($field->code)) {
                    continue;
                }

                $categorySuffix = $field->garment_category_id
                    ? Str::upper(Str::snake((string) ($categories[$field->garment_category_id] ?? 'CATEGORY')))
                    : 'GLOBAL';
                DB::table('measurement_fields')->where('id', $field->id)->update([
                    'code' => $this->uniqueCode(
                        $this->preferredCode($field).'_'.$categorySuffix,
                        (int) $field->id,
                    ),
                ]);
            }

            $report['ambiguous'][] = [
                'normalized_name' => $normalizedName,
                'field_ids' => $group->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ];
        }

        foreach (DB::table('measurement_fields')->orderBy('id')->get() as $field) {
            $code = $this->uniqueCode($this->preferredCode($field), (int) $field->id);
            if ($field->code !== $code) {
                DB::table('measurement_fields')->where('id', $field->id)->update(['code' => $code]);
            }
        }

        return $report;
    }

    private function upsertApplicability(int $categoryId, int $fieldId, bool $isRequired, int $sortOrder): void
    {
        $existing = DB::table('garment_category_measurement_field')
            ->where('garment_category_id', $categoryId)
            ->where('measurement_field_id', $fieldId)
            ->first();

        DB::table('garment_category_measurement_field')->updateOrInsert(
            ['garment_category_id' => $categoryId, 'measurement_field_id' => $fieldId],
            [
                'is_required' => (bool) ($existing?->is_required || $isRequired),
                'sort_order' => $existing ? min((int) $existing->sort_order, $sortOrder) : $sortOrder,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );
    }

    private function preferredCode(object $field): string
    {
        $candidate = filled($field->code ?? null) ? (string) $field->code : (string) $field->name;
        $code = Str::upper(Str::snake(Str::lower(Str::ascii($candidate))));
        $code = preg_replace('/[^A-Z0-9_]+/', '_', $code) ?? '';
        $code = trim(preg_replace('/_+/', '_', $code) ?? '', '_');

        return $code !== '' && preg_match('/^[A-Z]/', $code) ? $code : 'MEASUREMENT_'.$field->id;
    }

    private function uniqueCode(string $candidate, int $fieldId): string
    {
        $candidate = Str::limit($candidate, 100, '');
        $code = $candidate;
        $suffix = 1;

        while (DB::table('measurement_fields')->where('code', $code)->where('id', '!=', $fieldId)->exists()) {
            $tail = '_'.$fieldId.($suffix > 1 ? '_'.$suffix : '');
            $code = Str::limit($candidate, 100 - strlen($tail), '').$tail;
            $suffix++;
        }

        return $code;
    }

    private function normalizedName(string $name): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', Str::ascii($name)) ?? $name));
    }

    private function normalizedUnit(?string $unit): string
    {
        $unit = Str::lower(trim((string) $unit));

        return in_array($unit, ['cm', 'in', 'kg'], true) ? $unit : 'cm';
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfMissing('measurement_profiles', 'lineage_uuid', function (Blueprint $table): void {
            $table->uuid('lineage_uuid')->nullable()->after('customer_id');
        });
        $this->addColumnIfMissing('measurement_profiles', 'revision', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->nullable()->after('lineage_uuid');
        });
        $this->addColumnIfMissing('measurement_profiles', 'is_current', function (Blueprint $table): void {
            $table->boolean('is_current')->default(false)->after('revision');
        });
        $this->addColumnIfMissing('measurement_profiles', 'current_lineage_key', function (Blueprint $table): void {
            $table->uuid('current_lineage_key')->nullable()->after('is_current');
        });
        $this->addColumnIfMissing('measurement_profiles', 'current_customer_profile_key', function (Blueprint $table): void {
            $table->string('current_customer_profile_key')->nullable()->after('current_lineage_key');
        });
        $this->addColumnIfMissing('measurement_profiles', 'measured_at', function (Blueprint $table): void {
            $table->timestamp('measured_at')->nullable()->after('gender_scope');
        });
        $this->addColumnIfMissing('measurement_profiles', 'recorded_by_user_id', function (Blueprint $table): void {
            $table->unsignedBigInteger('recorded_by_user_id')->nullable()->after('measured_at');
        });
        $this->addColumnIfMissing('measurement_values', 'field_code_snapshot', function (Blueprint $table): void {
            $table->string('field_code_snapshot', 100)->nullable()->after('measurement_field_id');
        });
        $this->addColumnIfMissing('measurement_values', 'field_label_snapshot', function (Blueprint $table): void {
            $table->string('field_label_snapshot')->nullable()->after('field_code_snapshot');
        });

        $this->reconcileInvalidCustomerOwnership();
        $this->reconcileOwnedProfiles();
        $this->reconcileUnownedProfiles();
        $this->reconcileValueSnapshots();

        $this->makeRequired('measurement_profiles', 'lineage_uuid', function (Blueprint $table): void {
            $table->uuid('lineage_uuid')->nullable(false)->change();
        });
        $this->makeRequired('measurement_profiles', 'revision', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->nullable(false)->change();
        });

        $this->ensureIndex('measurement_profiles', 'measurement_profiles_is_current_index', ['is_current'], false, function (Blueprint $table): void {
            $table->index('is_current', 'measurement_profiles_is_current_index');
        });
        $this->ensureIndex('measurement_profiles', 'measurement_profiles_current_lineage_key_unique', ['current_lineage_key'], true, function (Blueprint $table): void {
            $table->unique('current_lineage_key', 'measurement_profiles_current_lineage_key_unique');
        });
        $this->ensureIndex('measurement_profiles', 'measurement_profiles_current_customer_profile_key_unique', ['current_customer_profile_key'], true, function (Blueprint $table): void {
            $table->unique('current_customer_profile_key', 'measurement_profiles_current_customer_profile_key_unique');
        });
        $this->ensureIndex('measurement_profiles', 'measurement_profiles_customer_current_index', ['customer_id', 'is_current'], false, function (Blueprint $table): void {
            $table->index(['customer_id', 'is_current'], 'measurement_profiles_customer_current_index');
        });
        $this->ensureIndex('measurement_profiles', 'measurement_profiles_lineage_revision_unique', ['lineage_uuid', 'revision'], true, function (Blueprint $table): void {
            $table->unique(['lineage_uuid', 'revision'], 'measurement_profiles_lineage_revision_unique');
        });
        $this->ensureIndex('measurement_values', 'measurement_values_field_index', ['measurement_field_id'], false, function (Blueprint $table): void {
            $table->index('measurement_field_id', 'measurement_values_field_index');
        });

        $this->ensureForeignKey(
            'measurement_profiles',
            'measurement_profiles_customer_id_foreign',
            ['customer_id'],
            'customers',
            ['id'],
            'set null',
            'no action',
            function (Blueprint $table): void {
                $table->foreign('customer_id', 'measurement_profiles_customer_id_foreign')
                    ->references('id')
                    ->on('customers')
                    ->nullOnDelete();
            },
        );
        $this->ensureForeignKey(
            'measurement_profiles',
            'measurement_profiles_recorded_by_user_id_foreign',
            ['recorded_by_user_id'],
            'users',
            ['id'],
            'set null',
            'no action',
            function (Blueprint $table): void {
                $table->foreign('recorded_by_user_id', 'measurement_profiles_recorded_by_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            },
        );
    }

    public function down(): void
    {
        Schema::table('measurement_values', function (Blueprint $table): void {
            if (Schema::hasIndex('measurement_values', 'measurement_values_field_index')) {
                $table->dropIndex('measurement_values_field_index');
            }
            $columns = array_values(array_filter(
                ['field_code_snapshot', 'field_label_snapshot'],
                fn (string $column): bool => Schema::hasColumn('measurement_values', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('measurement_profiles', function (Blueprint $table): void {
            if ($this->hasForeignKeyByColumns('measurement_profiles', ['customer_id'])) {
                $table->dropForeign(['customer_id']);
            }
            if ($this->hasForeignKeyByColumns('measurement_profiles', ['recorded_by_user_id'])) {
                $table->dropForeign(['recorded_by_user_id']);
            }
            if (Schema::hasIndex('measurement_profiles', 'measurement_profiles_lineage_revision_unique')) {
                $table->dropUnique('measurement_profiles_lineage_revision_unique');
            }
            if (Schema::hasIndex('measurement_profiles', 'measurement_profiles_current_lineage_key_unique')) {
                $table->dropUnique('measurement_profiles_current_lineage_key_unique');
            }
            if (Schema::hasIndex('measurement_profiles', 'measurement_profiles_current_customer_profile_key_unique')) {
                $table->dropUnique('measurement_profiles_current_customer_profile_key_unique');
            }
            if (Schema::hasIndex('measurement_profiles', 'measurement_profiles_customer_current_index')) {
                $table->dropIndex('measurement_profiles_customer_current_index');
            }
            if (Schema::hasIndex('measurement_profiles', 'measurement_profiles_is_current_index')) {
                $table->dropIndex('measurement_profiles_is_current_index');
            }
            $columns = array_values(array_filter(
                ['lineage_uuid', 'revision', 'is_current', 'current_lineage_key', 'current_customer_profile_key', 'measured_at', 'recorded_by_user_id'],
                fn (string $column): bool => Schema::hasColumn('measurement_profiles', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function addColumnIfMissing(string $table, string $column, callable $definition): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    private function makeRequired(string $table, string $column, callable $definition): void
    {
        $metadata = collect(Schema::getColumns($table))->first(
            fn (array $candidate): bool => Str::lower($candidate['name']) === Str::lower($column),
        );
        if (! $metadata || ! $metadata['nullable']) {
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    private function ensureIndex(string $table, string $name, array $columns, bool $unique, callable $definition): void
    {
        $expectedColumns = $this->normalizeIdentifiers($columns);
        foreach (Schema::getIndexes($table) as $index) {
            $sameName = Str::lower((string) $index['name']) === Str::lower($name);
            $equivalent = $this->normalizeIdentifiers($index['columns']) === $expectedColumns
                && (bool) $index['unique'] === $unique;
            if ($equivalent) {
                return;
            }
            if ($sameName) {
                throw new RuntimeException("Index [$name] already exists on [$table] with incompatible semantics.");
            }
        }
        Schema::table($table, function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    private function ensureForeignKey(
        string $table,
        string $name,
        array $columns,
        string $foreignTable,
        array $foreignColumns,
        string $onDelete,
        string $onUpdate,
        callable $definition,
    ): void {
        $expectedColumns = $this->normalizeIdentifiers($columns);
        $expectedForeignColumns = $this->normalizeIdentifiers($foreignColumns);
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $sameName = filled($foreignKey['name'] ?? null)
                && Str::lower((string) $foreignKey['name']) === Str::lower($name);
            $sameLocalColumns = $this->normalizeIdentifiers($foreignKey['columns']) === $expectedColumns;
            $equivalent = $sameLocalColumns
                && Str::lower((string) $foreignKey['foreign_table']) === Str::lower($foreignTable)
                && $this->normalizeIdentifiers($foreignKey['foreign_columns']) === $expectedForeignColumns
                && $this->normalizeReferentialAction((string) $foreignKey['on_delete']) === $this->normalizeReferentialAction($onDelete)
                && $this->normalizeReferentialAction((string) $foreignKey['on_update']) === $this->normalizeReferentialAction($onUpdate);
            if ($equivalent) {
                return;
            }
            if ($sameName || $sameLocalColumns) {
                throw new RuntimeException("Foreign key [$name] already exists on [$table] with incompatible semantics.");
            }
        }
        Schema::table($table, function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    private function reconcileInvalidCustomerOwnership(): void
    {
        DB::table('measurement_profiles')
            ->whereNotNull('customer_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('customers')
                    ->whereColumn('customers.id', 'measurement_profiles.customer_id');
            })
            ->update(['customer_id' => null, 'current_customer_profile_key' => null]);
    }

    private function reconcileOwnedProfiles(): void
    {
        $groups = DB::table('measurement_profiles')
            ->whereNotNull('customer_id')
            ->orderBy('customer_id')
            ->orderBy('profile_name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'customer_id', 'profile_name', 'created_at', 'lineage_uuid', 'revision', 'is_current', 'current_lineage_key', 'current_customer_profile_key', 'measured_at'])
            ->groupBy(fn (object $profile): string => $profile->customer_id.'|'.Str::lower(trim($profile->profile_name ?: 'Default')));

        foreach ($groups as $profiles) {
            $profiles = $profiles->values();
            $existing = $profiles->first(fn (object $profile): bool => $this->validUuid($profile->lineage_uuid));
            $groupLineage = $existing?->lineage_uuid ?? (string) Str::uuid();
            $states = $profiles->map(fn (object $profile): array => [
                'profile' => $profile,
                'lineage_uuid' => $this->validUuid($profile->lineage_uuid) ? (string) $profile->lineage_uuid : $groupLineage,
                'revision' => $this->validRevision($profile->revision) ? (int) $profile->revision : null,
            ]);

            foreach ($states->groupBy('lineage_uuid', preserveKeys: true) as $lineageStates) {
                $used = $lineageStates->pluck('revision')->filter()->map(fn ($revision): int => (int) $revision)->flip();
                $candidate = 1;
                foreach ($lineageStates as $stateIndex => $state) {
                    if ($state['revision'] !== null) {
                        continue;
                    }
                    while ($used->has($candidate)) {
                        $candidate++;
                    }
                    $states[$stateIndex] = [...$state, 'revision' => $candidate];
                    $used->put($candidate, true);
                    $candidate++;
                }
            }

            $current = $states->filter(fn (array $state): bool => (bool) $state['profile']->is_current);
            if ($current->count() === 1) {
                $currentId = (int) $current->first()['profile']->id;
            } elseif ($current->isNotEmpty()) {
                $expectedKey = $this->customerProfileKey((int) $profiles->first()->customer_id, (string) $profiles->first()->profile_name);
                $preferred = $current->first(fn (array $state): bool => $state['profile']->current_customer_profile_key === $expectedKey);
                $currentId = (int) ($preferred ?? $current->last())['profile']->id;
            } else {
                $currentId = (int) $states->last()['profile']->id;
            }

            foreach ($states->filter(fn (array $state): bool => (int) $state['profile']->id !== $currentId) as $state) {
                $this->updateProfileState($state, false);
            }
            $this->updateProfileState(
                $states->first(fn (array $state): bool => (int) $state['profile']->id === $currentId),
                true,
            );
        }
    }

    private function reconcileUnownedProfiles(): void
    {
        DB::table('measurement_profiles')
            ->whereNull('customer_id')
            ->orderBy('id')
            ->get(['id', 'customer_id', 'profile_name', 'created_at', 'lineage_uuid', 'revision', 'is_current', 'current_lineage_key', 'current_customer_profile_key', 'measured_at'])
            ->each(function (object $profile): void {
                $lineage = $this->validUuid($profile->lineage_uuid) ? (string) $profile->lineage_uuid : (string) Str::uuid();
                $this->updateProfileState([
                    'profile' => $profile,
                    'lineage_uuid' => $lineage,
                    'revision' => $this->validRevision($profile->revision) ? (int) $profile->revision : 1,
                ], true);
            });
    }

    private function updateProfileState(array $state, bool $isCurrent): void
    {
        $profile = $state['profile'];
        $lineage = (string) $state['lineage_uuid'];
        $revision = (int) $state['revision'];
        $payload = [];
        if ((string) $profile->lineage_uuid !== $lineage) {
            $payload['lineage_uuid'] = $lineage;
        }
        if ((int) $profile->revision !== $revision) {
            $payload['revision'] = $revision;
        }
        if ($profile->measured_at === null) {
            $payload['measured_at'] = $profile->created_at;
        }
        if ((bool) $profile->is_current !== $isCurrent) {
            $payload['is_current'] = $isCurrent;
        }
        $currentLineageKey = $isCurrent ? $lineage : null;
        if ($profile->current_lineage_key !== $currentLineageKey) {
            $payload['current_lineage_key'] = $currentLineageKey;
        }
        $currentCustomerProfileKey = $isCurrent && $profile->customer_id !== null
            ? $this->customerProfileKey((int) $profile->customer_id, (string) $profile->profile_name)
            : null;
        if ($profile->current_customer_profile_key !== $currentCustomerProfileKey) {
            $payload['current_customer_profile_key'] = $currentCustomerProfileKey;
        }
        if ($payload !== []) {
            DB::table('measurement_profiles')->where('id', $profile->id)->update($payload);
        }
    }

    private function reconcileValueSnapshots(): void
    {
        DB::table('measurement_values')
            ->where(function ($query): void {
                $query->whereNull('field_code_snapshot')
                    ->orWhere('field_code_snapshot', '')
                    ->orWhereNull('field_label_snapshot')
                    ->orWhere('field_label_snapshot', '');
            })
            ->orderBy('id')
            ->chunkById(500, function ($values): void {
                $fields = DB::table('measurement_fields')
                    ->whereIn('id', $values->pluck('measurement_field_id')->unique())
                    ->get(['id', 'code', 'name'])
                    ->keyBy('id');
                foreach ($values as $value) {
                    $field = $fields->get($value->measurement_field_id);
                    if (! $field) {
                        continue;
                    }
                    $payload = [];
                    if (blank($value->field_code_snapshot)) {
                        $payload['field_code_snapshot'] = $field->code;
                    }
                    if (blank($value->field_label_snapshot)) {
                        $payload['field_label_snapshot'] = $field->name;
                    }
                    if ($payload !== []) {
                        DB::table('measurement_values')->where('id', $value->id)->update($payload);
                    }
                }
            });
    }

    private function hasForeignKeyByColumns(string $table, array $columns): bool
    {
        $expected = $this->normalizeIdentifiers($columns);

        return collect(Schema::getForeignKeys($table))->contains(
            fn (array $foreignKey): bool => $this->normalizeIdentifiers($foreignKey['columns']) === $expected,
        );
    }

    private function validUuid(mixed $value): bool
    {
        return is_string($value) && Str::isUuid($value);
    }

    private function validRevision(mixed $value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }

    private function normalizeIdentifiers(array $identifiers): array
    {
        return array_map(fn ($identifier): string => Str::lower((string) $identifier), array_values($identifiers));
    }

    private function normalizeReferentialAction(string $action): string
    {
        $normalized = Str::of($action)->lower()->replace('_', ' ')->squish()->toString();

        return $normalized === 'restrict' ? 'no action' : $normalized;
    }

    private function customerProfileKey(int $customerId, string $profileName): string
    {
        return $customerId.':'.hash('sha256', Str::lower(trim($profileName ?: 'Default')));
    }
};

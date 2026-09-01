<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\MeasurementField;
use App\Services\Measurements\CustomerMeasurementProfileService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMeasurementsMigrationHotfixTest extends TestCase
{
    public function test_clean_pre_000003_schema_applies_and_a_second_retry_is_a_no_op(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->assertFalse(Schema::hasColumn('measurement_profiles', 'lineage_uuid'));
        $this->assertFalse(Schema::hasColumn('measurement_values', 'field_code_snapshot'));

        $migration->up();
        $firstIndexes = Schema::getIndexes('measurement_profiles');
        $firstForeignKeys = Schema::getForeignKeys('measurement_profiles');

        $migration->up();

        $this->assertTrue(Schema::hasColumns('measurement_profiles', [
            'lineage_uuid',
            'revision',
            'is_current',
            'current_lineage_key',
            'current_customer_profile_key',
            'measured_at',
            'recorded_by_user_id',
        ]));
        $this->assertTrue(Schema::hasColumns('measurement_values', [
            'field_code_snapshot',
            'field_label_snapshot',
        ]));
        $this->assertFalse($this->column('measurement_profiles', 'lineage_uuid')['nullable']);
        $this->assertFalse($this->column('measurement_profiles', 'revision')['nullable']);
        $this->assertIndex('measurement_profiles', ['lineage_uuid', 'revision'], true);
        $this->assertIndex('measurement_profiles', ['current_lineage_key'], true);
        $this->assertIndex('measurement_profiles', ['current_customer_profile_key'], true);
        $this->assertIndex('measurement_profiles', ['customer_id', 'is_current'], false);
        $this->assertIndex('measurement_profiles', ['is_current'], false);
        $this->assertIndex('measurement_values', ['measurement_field_id'], false);
        $this->assertForeignKey('measurement_profiles', ['customer_id'], 'customers', ['id'], 'set null');
        $this->assertForeignKey('measurement_profiles', ['recorded_by_user_id'], 'users', ['id'], 'set null');
        $this->assertEquals($firstIndexes, Schema::getIndexes('measurement_profiles'));
        $this->assertEquals($firstForeignKeys, Schema::getForeignKeys('measurement_profiles'));
    }

    public function test_partial_schema_and_reconciliation_resume_without_rewriting_valid_history(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->createPartialSchema();

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $waist = $this->field('Legacy Waist', 'LEGACY_WAIST');
        $chest = $this->field('Legacy Chest', 'LEGACY_CHEST');
        $lineage = (string) Str::uuid();
        $unownedLineage = (string) Str::uuid();

        $firstId = DB::table('measurement_profiles')->insertGetId([
            'customer_id' => $customer->id,
            'lineage_uuid' => $lineage,
            'revision' => 1,
            'is_current' => false,
            'profile_name' => 'Default',
            'measured_at' => '2025-12-31 10:00:00',
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-01 10:00:00',
        ]);
        $secondId = DB::table('measurement_profiles')->insertGetId([
            'customer_id' => $customer->id,
            'lineage_uuid' => null,
            'revision' => null,
            'is_current' => false,
            'profile_name' => 'Default',
            'created_at' => '2026-02-01 10:00:00',
            'updated_at' => '2026-02-01 10:00:00',
        ]);
        $unownedId = DB::table('measurement_profiles')->insertGetId([
            'customer_id' => null,
            'lineage_uuid' => $unownedLineage,
            'revision' => 1,
            'is_current' => true,
            'current_lineage_key' => $unownedLineage,
            'profile_name' => 'Imported Unknown',
            'measured_at' => '2026-03-01 10:00:00',
            'created_at' => '2026-03-01 10:00:00',
            'updated_at' => '2026-03-01 10:00:00',
        ]);
        DB::table('measurement_values')->insert([
            [
                'measurement_profile_id' => $firstId,
                'measurement_field_id' => $waist->id,
                'field_code_snapshot' => 'ORIGINAL_WAIST',
                'field_label_snapshot' => 'Original historical waist',
                'value' => '36.25',
                'unit' => 'in',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'measurement_profile_id' => $secondId,
                'measurement_field_id' => $chest->id,
                'field_code_snapshot' => null,
                'field_label_snapshot' => null,
                'value' => '42.00',
                'unit' => 'in',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $valueIndexesBefore = Schema::getIndexes('measurement_values');
        $migration->up();

        $first = DB::table('measurement_profiles')->find($firstId);
        $second = DB::table('measurement_profiles')->find($secondId);
        $unowned = DB::table('measurement_profiles')->find($unownedId);
        $firstValue = DB::table('measurement_values')->where('measurement_profile_id', $firstId)->first();
        $secondValue = DB::table('measurement_values')->where('measurement_profile_id', $secondId)->first();

        $this->assertSame($lineage, $first->lineage_uuid);
        $this->assertSame(1, $first->revision);
        $this->assertSame('2025-12-31 10:00:00', $first->measured_at);
        $this->assertSame($lineage, $second->lineage_uuid);
        $this->assertSame(2, $second->revision);
        $this->assertSame(0, $first->is_current);
        $this->assertSame(1, $second->is_current);
        $this->assertSame($lineage, $second->current_lineage_key);
        $this->assertSame(CustomerMeasurementProfileService::customerProfileKey($customer->id), $second->current_customer_profile_key);
        $this->assertSame($unownedLineage, $unowned->lineage_uuid);
        $this->assertSame(1, $unowned->revision);
        $this->assertSame(1, $unowned->is_current);
        $this->assertSame('ORIGINAL_WAIST', $firstValue->field_code_snapshot);
        $this->assertSame('Original historical waist', $firstValue->field_label_snapshot);
        $this->assertSame('LEGACY_CHEST', $secondValue->field_code_snapshot);
        $this->assertSame('Legacy Chest', $secondValue->field_label_snapshot);
        $this->assertDatabaseCount('measurement_profiles', 3);
        $this->assertDatabaseCount('measurement_values', 2);
        $this->assertEquals($valueIndexesBefore, Schema::getIndexes('measurement_values'));
        $this->assertCount(0, Schema::getForeignKeys('measurement_values'));

        $stateAfterCompletion = DB::table('measurement_profiles')->orderBy('id')->get()->map(fn (object $profile): array => (array) $profile)->all();
        $valuesAfterCompletion = DB::table('measurement_values')->orderBy('id')->get()->map(fn (object $value): array => (array) $value)->all();

        $migration->up();

        $this->assertSame($stateAfterCompletion, DB::table('measurement_profiles')->orderBy('id')->get()->map(fn (object $profile): array => (array) $profile)->all());
        $this->assertSame($valuesAfterCompletion, DB::table('measurement_values')->orderBy('id')->get()->map(fn (object $value): array => (array) $value)->all());
        $this->assertForeignKey('measurement_profiles', ['customer_id'], 'customers', ['id'], 'set null');
        $this->assertForeignKey('measurement_profiles', ['recorded_by_user_id'], 'users', ['id'], 'set null');
    }

    private function createPartialSchema(): void
    {
        Schema::table('measurement_profiles', function (Blueprint $table): void {
            $table->uuid('lineage_uuid')->nullable()->after('customer_id');
            $table->unsignedInteger('revision')->nullable()->after('lineage_uuid');
            $table->boolean('is_current')->default(false)->after('revision');
            $table->uuid('current_lineage_key')->nullable()->after('is_current');
            $table->string('current_customer_profile_key')->nullable()->after('current_lineage_key');
            $table->timestamp('measured_at')->nullable()->after('gender_scope');
            $table->unsignedBigInteger('recorded_by_user_id')->nullable()->after('measured_at');
            $table->index('is_current', 'measurement_profiles_is_current_index');
            $table->unique('current_lineage_key', 'measurement_profiles_current_lineage_key_unique');
            $table->unique('current_customer_profile_key', 'measurement_profiles_current_customer_profile_key_unique');
            $table->index(['customer_id', 'is_current'], 'measurement_profiles_customer_current_index');
            $table->unique(['lineage_uuid', 'revision'], 'measurement_profiles_lineage_revision_unique');
            $table->foreign('customer_id', 'measurement_profiles_customer_id_foreign')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('recorded_by_user_id', 'measurement_profiles_recorded_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('measurement_values', function (Blueprint $table): void {
            $table->string('field_code_snapshot', 100)->nullable()->after('measurement_field_id');
            $table->string('field_label_snapshot')->nullable()->after('field_code_snapshot');
            $table->index('measurement_field_id', 'measurement_values_field_index');
            $table->index('measurement_field_id', 'measurement_values_measurement_field_id_foreign');
        });
    }

    private function field(string $name, string $code): MeasurementField
    {
        return MeasurementField::query()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'code' => $code,
            'unit' => 'cm',
            'default_unit' => 'cm',
            'is_global' => true,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_24_000003_create_customer_measurement_revision_foundation.php');
    }

    private function column(string $table, string $column): array
    {
        return collect(Schema::getColumns($table))->firstWhere('name', $column);
    }

    private function assertIndex(string $table, array $columns, bool $unique): void
    {
        $this->assertTrue(collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => $index['columns'] === $columns && (bool) $index['unique'] === $unique,
        ));
    }

    private function assertForeignKey(string $table, array $columns, string $foreignTable, array $foreignColumns, string $onDelete): void
    {
        $this->assertTrue(collect(Schema::getForeignKeys($table))->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === $columns
                && $foreignKey['foreign_table'] === $foreignTable
                && $foreignKey['foreign_columns'] === $foreignColumns
                && str_replace('_', ' ', strtolower($foreignKey['on_delete'])) === $onDelete,
        ));
    }
}

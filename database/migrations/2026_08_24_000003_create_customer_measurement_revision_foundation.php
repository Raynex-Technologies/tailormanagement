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
        Schema::table('measurement_profiles', function (Blueprint $table): void {
            $table->uuid('lineage_uuid')->nullable()->after('customer_id');
            $table->unsignedInteger('revision')->nullable()->after('lineage_uuid');
            $table->boolean('is_current')->default(false)->index()->after('revision');
            $table->uuid('current_lineage_key')->nullable()->unique()->after('is_current');
            $table->string('current_customer_profile_key')->nullable()->unique()->after('current_lineage_key');
            $table->timestamp('measured_at')->nullable()->after('gender_scope');
            $table->foreignId('recorded_by_user_id')->nullable()->after('measured_at')->constrained('users')->nullOnDelete();
            $table->index(['customer_id', 'is_current'], 'measurement_profiles_customer_current_index');
        });

        Schema::table('measurement_values', function (Blueprint $table): void {
            $table->string('field_code_snapshot', 100)->nullable()->after('measurement_field_id');
            $table->string('field_label_snapshot')->nullable()->after('field_code_snapshot');
            $table->index('measurement_field_id', 'measurement_values_field_index');
        });

        DB::table('measurement_profiles')
            ->whereNotNull('customer_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('customers')
                    ->whereColumn('customers.id', 'measurement_profiles.customer_id');
            })
            ->update(['customer_id' => null]);

        $ownedProfiles = DB::table('measurement_profiles')
            ->whereNotNull('customer_id')
            ->orderBy('customer_id')
            ->orderBy('profile_name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'customer_id', 'profile_name', 'created_at'])
            ->groupBy(fn (object $profile): string => $profile->customer_id.'|'.Str::lower(trim($profile->profile_name ?: 'Default')));

        foreach ($ownedProfiles as $profiles) {
            $lineage = (string) Str::uuid();
            $lastIndex = $profiles->count() - 1;

            foreach ($profiles->values() as $index => $profile) {
                $isCurrent = $index === $lastIndex;
                DB::table('measurement_profiles')->where('id', $profile->id)->update([
                    'lineage_uuid' => $lineage,
                    'revision' => $index + 1,
                    'is_current' => $isCurrent,
                    'current_lineage_key' => $isCurrent ? $lineage : null,
                    'current_customer_profile_key' => $isCurrent
                        ? $this->customerProfileKey((int) $profile->customer_id, (string) $profile->profile_name)
                        : null,
                    'measured_at' => $profile->created_at,
                ]);
            }
        }

        DB::table('measurement_profiles')
            ->whereNull('customer_id')
            ->orderBy('id')
            ->get(['id', 'created_at'])
            ->each(function (object $profile): void {
                $lineage = (string) Str::uuid();
                DB::table('measurement_profiles')->where('id', $profile->id)->update([
                    'lineage_uuid' => $lineage,
                    'revision' => 1,
                    'is_current' => true,
                    'current_lineage_key' => $lineage,
                    'measured_at' => $profile->created_at,
                ]);
            });

        DB::table('measurement_values')
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

                    DB::table('measurement_values')->where('id', $value->id)->update([
                        'field_code_snapshot' => $field->code,
                        'field_label_snapshot' => $field->name,
                    ]);
                }
            });

        Schema::table('measurement_profiles', function (Blueprint $table): void {
            $table->uuid('lineage_uuid')->nullable(false)->change();
            $table->unsignedInteger('revision')->nullable(false)->change();
            $table->unique(['lineage_uuid', 'revision'], 'measurement_profiles_lineage_revision_unique');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('measurement_values', function (Blueprint $table): void {
            $table->dropIndex('measurement_values_field_index');
            $table->dropColumn(['field_code_snapshot', 'field_label_snapshot']);
        });

        Schema::table('measurement_profiles', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropUnique('measurement_profiles_lineage_revision_unique');
            $table->dropUnique(['current_lineage_key']);
            $table->dropUnique(['current_customer_profile_key']);
            $table->dropIndex('measurement_profiles_customer_current_index');
            $table->dropIndex(['is_current']);
            $table->dropConstrainedForeignId('recorded_by_user_id');
            $table->dropColumn([
                'lineage_uuid',
                'revision',
                'is_current',
                'current_lineage_key',
                'current_customer_profile_key',
                'measured_at',
            ]);
        });
    }

    private function customerProfileKey(int $customerId, string $profileName): string
    {
        return $customerId.':'.hash('sha256', Str::lower(trim($profileName ?: 'Default')));
    }
};

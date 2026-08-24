<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_measurements', function (Blueprint $table): void {
            $table->unsignedBigInteger('active_order_line_id')->nullable()->after('order_line_id');
            $table->foreignId('source_measurement_profile_id')->nullable()->after('measurements')->constrained('measurement_profiles')->nullOnDelete();
            $table->uuid('source_profile_lineage')->nullable()->after('source_measurement_profile_id');
            $table->unsignedInteger('source_profile_revision')->nullable()->after('source_profile_lineage');
            $table->string('source_profile_name')->nullable()->after('source_profile_revision');
            $table->timestamp('source_measured_at')->nullable()->after('source_profile_name');
            $table->unsignedTinyInteger('snapshot_format_version')->default(1)->after('source_measured_at');
            $table->index('source_profile_lineage', 'order_measurements_source_lineage_index');
        });

        DB::table('order_measurements')
            ->whereNull('deleted_at')
            ->orderBy('order_line_id')
            ->orderBy('id')
            ->get(['id', 'order_line_id'])
            ->groupBy('order_line_id')
            ->filter(fn ($rows): bool => $rows->count() === 1)
            ->each(function ($rows): void {
                $measurement = $rows->first();
                DB::table('order_measurements')->where('id', $measurement->id)->update([
                    'active_order_line_id' => $measurement->order_line_id,
                ]);
            });

        Schema::table('order_measurements', function (Blueprint $table): void {
            $table->unique('active_order_line_id', 'order_measurements_one_active_per_line_unique');
        });
    }

    public function down(): void
    {
        Schema::table('order_measurements', function (Blueprint $table): void {
            $table->dropUnique('order_measurements_one_active_per_line_unique');
            $table->dropIndex('order_measurements_source_lineage_index');
            $table->dropConstrainedForeignId('source_measurement_profile_id');
            $table->dropColumn([
                'active_order_line_id',
                'source_profile_lineage',
                'source_profile_revision',
                'source_profile_name',
                'source_measured_at',
                'snapshot_format_version',
            ]);
        });
    }
};

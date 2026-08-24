<?php

use App\Services\Measurements\MeasurementFieldReconciliationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measurement_fields', function (Blueprint $table): void {
            $table->string('code', 100)->nullable()->after('slug');
            $table->string('default_unit', 10)->nullable()->after('unit');
            $table->text('instructions')->nullable()->after('default_unit');
            $table->boolean('is_global')->default(false)->index()->after('instructions');
        });

        Schema::create('garment_category_measurement_field', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('garment_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_field_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['garment_category_id', 'measurement_field_id'], 'garment_category_measurement_unique');
            $table->index(['measurement_field_id', 'sort_order'], 'measurement_category_sort_index');
        });

        $report = app(MeasurementFieldReconciliationService::class)->reconcile();
        if ($report['ambiguous'] !== []) {
            Log::warning('Measurement definitions require manual semantic review.', $report['ambiguous']);
        }

        Schema::table('measurement_fields', function (Blueprint $table): void {
            $table->string('code', 100)->nullable(false)->change();
            $table->string('default_unit', 10)->nullable(false)->default('cm')->change();
            $table->unique('code', 'measurement_fields_code_unique');
        });

        Schema::table('order_catalog_items', function (Blueprint $table): void {
            $table->foreignId('garment_category_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_catalog_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('garment_category_id');
        });

        Schema::dropIfExists('garment_category_measurement_field');

        Schema::table('measurement_fields', function (Blueprint $table): void {
            $table->dropUnique('measurement_fields_code_unique');
            $table->dropIndex(['is_global']);
            $table->dropColumn(['code', 'default_unit', 'instructions', 'is_global']);
        });
    }
};

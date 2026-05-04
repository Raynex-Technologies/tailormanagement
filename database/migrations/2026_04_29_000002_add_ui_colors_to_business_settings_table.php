<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_settings')) {
            return;
        }

        Schema::table('business_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('business_settings', 'ui_primary_color')) {
                $table->string('ui_primary_color', 20)->nullable()->after('logo_path');
            }

            if (! Schema::hasColumn('business_settings', 'ui_secondary_color_1')) {
                $table->string('ui_secondary_color_1', 20)->nullable()->after('ui_primary_color');
            }

            if (! Schema::hasColumn('business_settings', 'ui_secondary_color_2')) {
                $table->string('ui_secondary_color_2', 20)->nullable()->after('ui_secondary_color_1');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_settings')) {
            return;
        }

        Schema::table('business_settings', function (Blueprint $table) {
            foreach (['ui_secondary_color_2', 'ui_secondary_color_1', 'ui_primary_color'] as $column) {
                if (Schema::hasColumn('business_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

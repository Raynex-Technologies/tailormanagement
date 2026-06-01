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
            if (! Schema::hasColumn('business_settings', 'allow_order_dates_flexibility')) {
                $table->boolean('allow_order_dates_flexibility')->default(false)->after('ui_secondary_color_2');
            }
        });
    }

    public function down(): void
    {
        // Intentionally no-op: database safety rules prohibit dropping stored settings data.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_sales') || Schema::hasColumn('pos_sales', 'receipt_token')) {
            return;
        }

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->string('receipt_token', 80)->nullable()->unique()->after('sale_number');
        });
    }

    public function down(): void
    {
        // Intentionally no-op: database safety rules prohibit dropping stored receipt access data.
    }
};

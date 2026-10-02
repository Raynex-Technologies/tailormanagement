<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->string('variation_description', 1000)->nullable();
        });
        Schema::table('order_package_template_items', function (Blueprint $table) {
            $table->string('variation_selection', 20)->nullable();
            $table->foreignId('inventory_stock_unit_id')->nullable()->constrained('inventory_stock_units', indexName: 'opti_stock_unit_fk')->restrictOnDelete();
            $table->foreignId('inventory_item_variant_id')->nullable()->constrained('inventory_item_variants', indexName: 'opti_variant_fk')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Order and package identities require a reviewed rollback; automatic historical destruction is disabled.');
    }
};

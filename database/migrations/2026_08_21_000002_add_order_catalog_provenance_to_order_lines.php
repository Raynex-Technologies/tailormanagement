<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->foreignId('order_catalog_item_id')
                ->nullable()
                ->after('inventory_item_variant_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('order_package_instance_id')
                ->nullable()
                ->after('order_catalog_item_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('order_package_template_item_id')
                ->nullable()
                ->after('order_package_instance_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['order_package_instance_id', 'id'], 'order_lines_package_instance_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropIndex('order_lines_package_instance_idx');
            $table->dropConstrainedForeignId('order_package_template_item_id');
            $table->dropConstrainedForeignId('order_package_instance_id');
            $table->dropConstrainedForeignId('order_catalog_item_id');
        });
    }
};

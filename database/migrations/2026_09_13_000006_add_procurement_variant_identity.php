<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_request_items' => 'pri6', 'purchase_order_items' => 'poi6', 'goods_receipt_items' => 'gri6', 'order_stock_request_items' => 'sri6'] as $table => $prefix) {
            Schema::table($table, function (Blueprint $table) use ($prefix) {
                $table->foreignId('inventory_stock_unit_id')->nullable()->constrained('inventory_stock_units', indexName: $prefix.'_unit_fk')->restrictOnDelete();
                $table->foreignId('inventory_item_variant_id')->nullable()->constrained('inventory_item_variants', indexName: $prefix.'_variant_fk')->restrictOnDelete();
                $table->string('variation_description', 1000)->nullable();
                $table->string('sku')->nullable();
            });
        }
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items', indexName: 'gri6_po_item_fk')->restrictOnDelete();
            $table->string('item_name')->nullable();
        });
        Schema::table('order_stock_request_items', fn (Blueprint $table) => $table->string('item_name')->nullable());
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('operation_key', 64)->nullable()->unique();
            $table->string('payload_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Procurement provenance must not be automatically destroyed.');
    }
};

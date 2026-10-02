<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('variant_mode', 20)->default('simple');
            $table->string('stock_identity_status', 30)->default('pending');
        });
        Schema::table('inventory_item_variants', fn (Blueprint $table) => $table->unique(['id', 'inventory_item_id'], 'inv_variant_parent_unique'));
        Schema::create('inventory_stock_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inventory_item_variant_id')->nullable()->unique();
            $table->foreign(['inventory_item_variant_id', 'inventory_item_id'], 'stock_unit_variant_parent_fk')
                ->references(['id', 'inventory_item_id'])->on('inventory_item_variants')->restrictOnDelete();
            // SQL NULL uniqueness cannot enforce the simple identity by itself.
            $table->unsignedBigInteger('simple_item_id')->nullable()->storedAs('CASE WHEN inventory_item_variant_id IS NULL THEN inventory_item_id ELSE NULL END')->unique();
            $table->string('sku', 100)->unique();
            $table->decimal('selling_price', 14, 2)->nullable();
            $table->decimal('reference_cost', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('allocation_status', 30)->default('ready');
            $table->timestamps();
        });
        Schema::create('inventory_stock_unit_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_stock_unit_id')->constrained()->restrictOnDelete();
            $table->string('barcode', 191)->unique();
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('primary_stock_unit_id')->nullable()->storedAs('CASE WHEN is_primary = 1 THEN inventory_stock_unit_id ELSE NULL END')->unique();
            $table->timestamps();
        });
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->foreignId('inventory_stock_unit_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
        // Keep legacy item balance uniqueness for this foundation. Variant units remain
        // unallocated: multi-unit balances and allocation are a separate cutover.
        foreach (['inventory_transactions', 'order_lines', 'pos_sale_items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreignId('inventory_stock_unit_id')->nullable()->constrained()->restrictOnDelete());
        }
        Schema::create('inventory_stock_unit_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_stock_unit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_stock_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->decimal('qty_on_hand', 14, 2);
            $table->decimal('qty_reserved', 14, 2);
            $table->decimal('legacy_movement_net', 18, 2);
            $table->unsignedBigInteger('last_legacy_transaction_id')->nullable();
            $table->string('reason')->default('migration_cutover_snapshot');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        // Never drop provenance after operational cutover automatically.
        if (\Illuminate\Support\Facades\DB::table('inventory_stock_units')->exists()) {
            throw new RuntimeException('Stock units exist. Reconcile and plan a reviewed rollback; do not discard operational identity.');
        }
        Schema::dropIfExists('inventory_stock_unit_baselines');
        foreach (['inventory_transactions', 'order_lines', 'pos_sale_items', 'inventory_stocks'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('inventory_stock_unit_id'));
        }
        Schema::dropIfExists('inventory_stock_unit_barcodes');
        Schema::dropIfExists('inventory_stock_units');
        Schema::table('inventory_item_variants', fn (Blueprint $table) => $table->dropUnique('inv_variant_parent_unique'));
        Schema::table('inventory_items', fn (Blueprint $table) => $table->dropColumn(['variant_mode', 'stock_identity_status']));
    }
};

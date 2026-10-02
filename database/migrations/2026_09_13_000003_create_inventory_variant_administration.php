<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_item_options')) {
            Schema::create('inventory_item_options', function (Blueprint $t) {
                $t->id();
                $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
                $t->string('name', 100);
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['inventory_item_id', 'name']);
            });
        }
        if (! Schema::hasTable('inventory_item_option_values')) {
            Schema::create('inventory_item_option_values', function (Blueprint $t) {
                $t->id();
                $t->foreignId('inventory_item_option_id')->constrained()->restrictOnDelete();
                $t->string('name', 100);
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['inventory_item_option_id', 'name'], 'inventory_option_value_name_unique');
            });
        }
        if (! Schema::hasTable('inventory_item_variant_option_value')) {
            Schema::create('inventory_item_variant_option_value', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('inventory_item_variant_id');
                $t->unsignedBigInteger('inventory_item_option_value_id');

            });
        }
        if (! Schema::hasColumn('inventory_item_variants', 'combination_key')) {
            Schema::table('inventory_item_variants', function (Blueprint $t) {
                $t->string('combination_key', 64)->nullable();
                $t->unique(['inventory_item_id', 'combination_key'], 'inventory_variant_combination_unique');
            });
        }
        $foreignNames = array_column(Schema::getForeignKeys('inventory_item_variant_option_value'), 'name');
        Schema::table('inventory_item_variant_option_value', function (Blueprint $t) use ($foreignNames) {
            if (! in_array('inv_variant_value_variant_fk', $foreignNames, true)) {
                $t->foreign('inventory_item_variant_id', 'inv_variant_value_variant_fk')->references('id')->on('inventory_item_variants')->restrictOnDelete();
            }
            if (! in_array('inv_variant_value_value_fk', $foreignNames, true)) {
                $t->foreign('inventory_item_option_value_id', 'inv_variant_value_value_fk')->references('id')->on('inventory_item_option_values')->restrictOnDelete();
            }
        });
        if (! Schema::hasIndex('inventory_item_variant_option_value', 'inventory_variant_value_unique')) {
            Schema::table('inventory_item_variant_option_value', fn (Blueprint $t) => $t->unique(['inventory_item_variant_id', 'inventory_item_option_value_id'], 'inventory_variant_value_unique'));
        }
        if (! Schema::hasIndex('inventory_stocks', 'inventory_stock_item_lookup')) {
            Schema::table('inventory_stocks', fn (Blueprint $t) => $t->index('inventory_item_id', 'inventory_stock_item_lookup'));
        }
        if (Schema::hasIndex('inventory_stocks', 'inventory_stocks_inventory_item_id_unique')) {
            Schema::table('inventory_stocks', function (Blueprint $t) {
                $t->dropUnique('inventory_stocks_inventory_item_id_unique');
            });
        }
        if (! Schema::hasTable('inventory_variant_allocations')) {
            Schema::create('inventory_variant_allocations', function (Blueprint $t) {
                $t->id();
                $t->foreignId('inventory_item_id')->unique()->constrained()->restrictOnDelete();
                $t->foreignId('source_stock_unit_id')->constrained('inventory_stock_units')->restrictOnDelete();
                $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $t->decimal('quantity', 14, 2);
                $t->json('allocations');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Variant identities and allocations require a reviewed rollback; no automatic data removal.');
    }
};

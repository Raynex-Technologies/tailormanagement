<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storefront_product_combos')) {
            Schema::create('storefront_product_combos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id');
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('featured_image_path')->nullable();
                $table->decimal('price', 14, 2)->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('storefront_is_visible')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['branch_id', 'slug'], 'storefront_product_combos_branch_slug_unique');
            });
        }

        $this->ensureForeignKey(
            table: 'storefront_product_combos',
            column: 'branch_id',
            referencedTable: 'branches',
            referencedColumn: 'id',
            foreignName: 'sf_prod_combos_branch_fk',
            onDelete: 'cascade'
        );

        if (! Schema::hasTable('storefront_product_combo_items')) {
            Schema::create('storefront_product_combo_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('storefront_product_combo_id');
                $table->foreignId('inventory_item_id');
                $table->decimal('quantity', 14, 2)->default(1);
                $table->timestamps();

                $table->unique(
                    ['storefront_product_combo_id', 'inventory_item_id'],
                    'storefront_product_combo_items_unique'
                );
            });
        }

        $this->ensureForeignKey(
            table: 'storefront_product_combo_items',
            column: 'storefront_product_combo_id',
            referencedTable: 'storefront_product_combos',
            referencedColumn: 'id',
            foreignName: 'sf_combo_items_combo_fk',
            onDelete: 'cascade'
        );

        $this->ensureForeignKey(
            table: 'storefront_product_combo_items',
            column: 'inventory_item_id',
            referencedTable: 'inventory_items',
            referencedColumn: 'id',
            foreignName: 'sf_combo_items_item_fk',
            onDelete: 'cascade'
        );

        if (! Schema::hasTable('storefront_coupons')) {
            Schema::create('storefront_coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id');
                $table->string('name');
                $table->string('code');
                $table->text('description')->nullable();
                $table->string('discount_type', 20)->default('fixed')->index();
                $table->decimal('discount_value', 14, 2);
                $table->decimal('min_subtotal', 14, 2)->nullable();
                $table->decimal('max_discount_amount', 14, 2)->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->unsignedInteger('per_customer_limit')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_auto_generated')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['branch_id', 'code'], 'storefront_coupons_branch_code_unique');
                $table->index(['branch_id', 'is_active'], 'storefront_coupons_branch_active_idx');
            });
        }

        $this->ensureForeignKey(
            table: 'storefront_coupons',
            column: 'branch_id',
            referencedTable: 'branches',
            referencedColumn: 'id',
            foreignName: 'sf_coupons_branch_fk',
            onDelete: 'cascade'
        );

        if (! Schema::hasTable('storefront_coupon_usages')) {
            Schema::create('storefront_coupon_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('storefront_coupon_id');
                $table->foreignId('order_id')->nullable();
                $table->foreignId('customer_id')->nullable();
                $table->timestamp('used_at')->useCurrent();

                $table->index(['storefront_coupon_id', 'customer_id'], 'storefront_coupon_usages_coupon_customer_idx');
            });
        }

        $this->ensureForeignKey(
            table: 'storefront_coupon_usages',
            column: 'storefront_coupon_id',
            referencedTable: 'storefront_coupons',
            referencedColumn: 'id',
            foreignName: 'sf_coupon_usages_coupon_fk',
            onDelete: 'cascade'
        );

        $this->ensureForeignKey(
            table: 'storefront_coupon_usages',
            column: 'order_id',
            referencedTable: 'orders',
            referencedColumn: 'id',
            foreignName: 'sf_coupon_usages_order_fk',
            onDelete: 'set null'
        );

        $this->ensureForeignKey(
            table: 'storefront_coupon_usages',
            column: 'customer_id',
            referencedTable: 'customers',
            referencedColumn: 'id',
            foreignName: 'sf_coupon_usages_customer_fk',
            onDelete: 'set null'
        );

        if (! Schema::hasColumn('orders', 'storefront_coupon_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('storefront_coupon_id')->nullable()->after('discount_total');
            });
        }

        if (! Schema::hasColumn('orders', 'coupon_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('coupon_code', 80)->nullable()->after('storefront_coupon_id');
            });
        }

        if (! Schema::hasColumn('orders', 'coupon_name')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('coupon_name')->nullable()->after('coupon_code');
            });
        }

        if (! Schema::hasColumn('orders', 'coupon_discount_type')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('coupon_discount_type', 20)->nullable()->after('coupon_name');
            });
        }

        if (! Schema::hasColumn('orders', 'coupon_discount_value')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('coupon_discount_value', 14, 2)->nullable()->after('coupon_discount_type');
            });
        }

        $this->ensureForeignKey(
            table: 'orders',
            column: 'storefront_coupon_id',
            referencedTable: 'storefront_coupons',
            referencedColumn: 'id',
            foreignName: 'orders_storefront_coupon_fk',
            onDelete: 'set null'
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'storefront_coupon_id')) {
                    $table->dropForeign('orders_storefront_coupon_fk');
                    $table->dropColumn('storefront_coupon_id');
                }

                $columns = array_values(array_filter([
                    Schema::hasColumn('orders', 'coupon_code') ? 'coupon_code' : null,
                    Schema::hasColumn('orders', 'coupon_name') ? 'coupon_name' : null,
                    Schema::hasColumn('orders', 'coupon_discount_type') ? 'coupon_discount_type' : null,
                    Schema::hasColumn('orders', 'coupon_discount_value') ? 'coupon_discount_value' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('storefront_coupon_usages');
        Schema::dropIfExists('storefront_coupons');
        Schema::dropIfExists('storefront_product_combo_items');
        Schema::dropIfExists('storefront_product_combos');
    }

    protected function ensureForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $foreignName,
        string $onDelete = 'cascade'
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || $this->hasConstraint($table, $foreignName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use (
            $column,
            $referencedTable,
            $referencedColumn,
            $foreignName,
            $onDelete
        ) {
            $foreign = $blueprint->foreign($column, $foreignName)
                ->references($referencedColumn)
                ->on($referencedTable);

            if ($onDelete === 'set null') {
                $foreign->nullOnDelete();
            } else {
                $foreign->cascadeOnDelete();
            }
        });
    }

    protected function hasConstraint(string $table, string $constraintName): bool
    {
        $databaseName = DB::connection()->getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $databaseName)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->exists();
    }
};

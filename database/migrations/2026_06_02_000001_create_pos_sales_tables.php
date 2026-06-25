<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_sales')) {
            Schema::create('pos_sales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('sale_number')->unique();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->decimal('subtotal', 14, 2);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->decimal('change_amount', 14, 2)->default(0);
                $table->string('payment_method', 50)->nullable();
                $table->string('payment_reference')->nullable();
                $table->string('status', 50)->default('completed');
                $table->text('notes')->nullable();
                $table->timestamp('sold_at');
                $table->timestamps();

                $table->index(['branch_id', 'sold_at']);
                $table->index(['customer_id', 'sold_at']);
                $table->index(['user_id', 'sold_at']);
            });
        } else {
            $this->ensurePosSalesIndexes();
        }

        if (! Schema::hasTable('pos_sale_items')) {
            Schema::create('pos_sale_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_sale_id');
                $table->unsignedBigInteger('inventory_item_id');
                $table->string('item_name');
                $table->string('sku', 100)->nullable();
                $table->decimal('unit_price', 14, 2);
                $table->decimal('quantity', 14, 2);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2);
                $table->timestamps();

                $table->index(['pos_sale_id', 'inventory_item_id']);
                $table->index('inventory_item_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
    }

    protected function ensurePosSalesIndexes(): void
    {
        if ($this->isSQLite()) {
            return;
        }

        Schema::table('pos_sales', function (Blueprint $table) {
            if (
                Schema::hasColumn('pos_sales', 'branch_id')
                && Schema::hasColumn('pos_sales', 'sold_at')
                && ! $this->indexExists('pos_sales', 'pos_sales_branch_id_sold_at_index')
            ) {
                $table->index(['branch_id', 'sold_at']);
            }

            if (
                Schema::hasColumn('pos_sales', 'customer_id')
                && Schema::hasColumn('pos_sales', 'sold_at')
                && ! $this->indexExists('pos_sales', 'pos_sales_customer_id_sold_at_index')
            ) {
                $table->index(['customer_id', 'sold_at']);
            }

            if (
                Schema::hasColumn('pos_sales', 'user_id')
                && Schema::hasColumn('pos_sales', 'sold_at')
                && ! $this->indexExists('pos_sales', 'pos_sales_user_id_sold_at_index')
            ) {
                $table->index(['user_id', 'sold_at']);
            }
        });
    }

    protected function isSQLite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $indexes = collect(DB::select("SHOW INDEX FROM {$table}"))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        return in_array($indexName, $indexes);
    }
};

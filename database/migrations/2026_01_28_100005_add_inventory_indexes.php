<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip index creation for SQLite (testing)
        if ($this->isSQLite()) {
            return;
        }

        // Add indexes for inventory_items if not exists
        if (Schema::hasTable('inventory_items')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                // Composite index for branch + sku searches
                if (! $this->indexExists('inventory_items', 'inv_items_branch_sku_idx')) {
                    $table->index(['branch_id', 'sku'], 'inv_items_branch_sku_idx');
                }

                // Composite index for branch + name searches
                if (! $this->indexExists('inventory_items', 'inv_items_branch_name_idx')) {
                    $table->index(['branch_id', 'name'], 'inv_items_branch_name_idx');
                }

                // Index for category filter
                if (! $this->indexExists('inventory_items', 'inv_items_category_idx')) {
                    $table->index('inventory_category_id', 'inv_items_category_idx');
                }
            });
        }

        // Add indexes for inventory_transactions if not exists
        if (Schema::hasTable('inventory_transactions')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                // Composite index for item + created_at (ledger queries)
                if (! $this->indexExists('inventory_transactions', 'inv_txn_item_created_idx')) {
                    $table->index(['inventory_item_id', 'created_at'], 'inv_txn_item_created_idx');
                }

                // Index for type filter
                if (! $this->indexExists('inventory_transactions', 'inv_txn_type_idx')) {
                    $table->index('type', 'inv_txn_type_idx');
                }
            });
        }

        // Add indexes for inventory_stocks if not exists
        if (Schema::hasTable('inventory_stocks')) {
            Schema::table('inventory_stocks', function (Blueprint $table) {
                // Unique index for item (one stock record per item)
                if (! $this->indexExists('inventory_stocks', 'inv_stocks_item_unique')) {
                    $table->unique('inventory_item_id', 'inv_stocks_item_unique');
                }
            });
        }
    }

    public function down(): void
    {
        if ($this->isSQLite()) {
            return;
        }

        if (Schema::hasTable('inventory_items')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropIndex('inv_items_branch_sku_idx');
                $table->dropIndex('inv_items_branch_name_idx');
                $table->dropIndex('inv_items_category_idx');
            });
        }

        if (Schema::hasTable('inventory_transactions')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->dropIndex('inv_txn_item_created_idx');
                $table->dropIndex('inv_txn_type_idx');
            });
        }

        if (Schema::hasTable('inventory_stocks')) {
            Schema::table('inventory_stocks', function (Blueprint $table) {
                $table->dropUnique('inv_stocks_item_unique');
            });
        }
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

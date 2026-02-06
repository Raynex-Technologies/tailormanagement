<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need branch_id for branch scoping.
     */
    protected array $tables = [
        'customers',
        'suppliers',
        'orders',
        'order_payments',
        'delivery_notes',
        'inventory_categories',
        'inventory_items',
        'inventory_stocks',
        'inventory_transactions',
        'order_stock_requests',
        'purchase_requests',
        'purchase_orders',
        'goods_receipts',
        'capital_allocations',
        'capital_transactions',
        'expense_categories',
        'expenses',
        'conversations',
        'todos',
        'sms_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('branch_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('branches')
                        ->nullOnDelete();

                    $table->index('branch_id');
                });
            }
        }

        // Add composite indexes for common query patterns
        $this->addCompositeIndexes();
    }

    public function down(): void
    {
        // Drop composite indexes first
        $this->dropCompositeIndexes();

        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropForeign(['branch_id']);
                    $table->dropIndex([$tableName . '_branch_id_index']);
                    $table->dropColumn('branch_id');
                });
            }
        }
    }

    protected function addCompositeIndexes(): void
    {
        // Orders: branch + created_at for reporting
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['branch_id', 'created_at'], 'orders_branch_created_idx');
                $table->index(['branch_id', 'status'], 'orders_branch_status_idx');
            });
        }

        // Inventory transactions: branch + created_at for ledger queries
        if (Schema::hasTable('inventory_transactions')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->index(['branch_id', 'created_at'], 'inv_trans_branch_created_idx');
            });
        }

        // Expenses: branch + expense_date for reporting
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->index(['branch_id', 'expense_date'], 'expenses_branch_date_idx');
            });
        }

        // Capital transactions: branch + created_at
        if (Schema::hasTable('capital_transactions')) {
            Schema::table('capital_transactions', function (Blueprint $table) {
                $table->index(['branch_id', 'created_at'], 'cap_trans_branch_created_idx');
            });
        }
    }

    protected function dropCompositeIndexes(): void
    {
        $indexes = [
            'orders' => ['orders_branch_created_idx', 'orders_branch_status_idx'],
            'inventory_transactions' => ['inv_trans_branch_created_idx'],
            'expenses' => ['expenses_branch_date_idx'],
            'capital_transactions' => ['cap_trans_branch_created_idx'],
        ];

        foreach ($indexes as $table => $indexNames) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($indexNames) {
                    foreach ($indexNames as $indexName) {
                        $table->dropIndex($indexName);
                    }
                });
            }
        }
    }
};

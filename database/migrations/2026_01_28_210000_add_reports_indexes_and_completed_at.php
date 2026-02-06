<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add completed_at to orders for turnaround tracking
        if (! Schema::hasColumn('orders', 'completed_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->after('due_date');
            });
        }

        // Skip index creation for SQLite (testing) - indexes are created differently
        if ($this->isSQLite()) {
            return;
        }

        // Add performance indexes for reports using raw SQL to check if index exists
        $this->addIndexIfNotExists('orders', 'orders_branch_id_created_at_index', ['branch_id', 'created_at']);
        $this->addIndexIfNotExists('orders', 'orders_branch_id_status_index', ['branch_id', 'status']);
        $this->addIndexIfNotExists('orders', 'orders_branch_id_payment_status_index', ['branch_id', 'payment_status']);
        $this->addIndexIfNotExists('orders', 'orders_completed_at_index', ['completed_at']);

        if (Schema::hasTable('order_payments')) {
            $this->addIndexIfNotExists('order_payments', 'order_payments_branch_id_paid_at_index', ['branch_id', 'paid_at']);
        }

        if (Schema::hasTable('expenses')) {
            $this->addIndexIfNotExists('expenses', 'expenses_branch_id_expense_date_index', ['branch_id', 'expense_date']);
        }

        if (Schema::hasTable('inventory_transactions')) {
            $this->addIndexIfNotExists('inventory_transactions', 'inventory_transactions_branch_id_created_at_index', ['branch_id', 'created_at']);
            $this->addIndexIfNotExists('inventory_transactions', 'inventory_transactions_item_created_at_index', ['inventory_item_id', 'created_at']);
        }

        if (Schema::hasTable('capital_transactions')) {
            $this->addIndexIfNotExists('capital_transactions', 'capital_transactions_branch_id_created_at_index', ['branch_id', 'created_at']);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });

        if ($this->isSQLite()) {
            return;
        }

        // Drop indexes if they exist
        $this->dropIndexIfExists('orders', 'orders_branch_id_created_at_index');
        $this->dropIndexIfExists('orders', 'orders_branch_id_status_index');
        $this->dropIndexIfExists('orders', 'orders_branch_id_payment_status_index');
        $this->dropIndexIfExists('orders', 'orders_completed_at_index');
        $this->dropIndexIfExists('order_payments', 'order_payments_branch_id_paid_at_index');
        $this->dropIndexIfExists('expenses', 'expenses_branch_id_expense_date_index');
        $this->dropIndexIfExists('inventory_transactions', 'inventory_transactions_branch_id_created_at_index');
        $this->dropIndexIfExists('inventory_transactions', 'inventory_transactions_item_created_at_index');
        $this->dropIndexIfExists('capital_transactions', 'capital_transactions_branch_id_created_at_index');
    }

    /**
     * Check if using SQLite.
     */
    protected function isSQLite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /**
     * Check if an index exists on a table (MySQL only).
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }

    /**
     * Add an index if it doesn't already exist (MySQL only).
     */
    protected function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        if (! $this->indexExists($table, $indexName)) {
            $columnsList = implode('`, `', $columns);
            DB::statement("CREATE INDEX `{$indexName}` ON `{$table}` (`{$columnsList}`)");
        }
    }

    /**
     * Drop an index if it exists (MySQL only).
     */
    protected function dropIndexIfExists(string $table, string $indexName): void
    {
        if (Schema::hasTable($table) && $this->indexExists($table, $indexName)) {
            DB::statement("DROP INDEX `{$indexName}` ON `{$table}`");
        }
    }
};

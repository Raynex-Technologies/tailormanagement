<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need unique constraint updates for branch scoping.
     * Format: table => [columns that need branch-scoped uniqueness]
     */
    protected array $tables = [
        'inventory_categories' => ['name', 'slug'],
        'expense_categories' => ['name'],
    ];

    public function up(): void
    {
        // Update inventory_categories
        if (Schema::hasTable('inventory_categories')) {
            Schema::table('inventory_categories', function (Blueprint $table) {
                // Drop existing unique constraints
                $table->dropUnique(['name']);
                $table->dropUnique(['slug']);

                // Add composite unique constraints with branch_id
                $table->unique(['branch_id', 'name'], 'inv_cat_branch_name_unique');
                $table->unique(['branch_id', 'slug'], 'inv_cat_branch_slug_unique');
            });
        }

        // Update expense_categories
        if (Schema::hasTable('expense_categories')) {
            Schema::table('expense_categories', function (Blueprint $table) {
                // Drop existing unique constraint
                $table->dropUnique(['name']);

                // Add composite unique constraint with branch_id
                $table->unique(['branch_id', 'name'], 'exp_cat_branch_name_unique');
            });
        }

        // Update customers - customer codes should be unique per branch
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'code')) {
            Schema::table('customers', function (Blueprint $table) {
                // Check if there's a unique constraint on code and drop it
                try {
                    $table->dropUnique(['code']);
                } catch (\Exception $e) {
                    // Constraint might not exist, continue
                }

                // Add composite unique constraint
                $table->unique(['branch_id', 'code'], 'customers_branch_code_unique');
            });
        }
    }

    public function down(): void
    {
        // Restore inventory_categories
        if (Schema::hasTable('inventory_categories')) {
            Schema::table('inventory_categories', function (Blueprint $table) {
                $table->dropUnique('inv_cat_branch_name_unique');
                $table->dropUnique('inv_cat_branch_slug_unique');

                $table->unique('name');
                $table->unique('slug');
            });
        }

        // Restore expense_categories
        if (Schema::hasTable('expense_categories')) {
            Schema::table('expense_categories', function (Blueprint $table) {
                $table->dropUnique('exp_cat_branch_name_unique');

                $table->unique('name');
            });
        }

        // Restore customers
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'code')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique('customers_branch_code_unique');

                $table->unique('code');
            });
        }
    }
};

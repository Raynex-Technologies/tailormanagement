<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'expense_subcategory_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('expense_subcategory_id')
                    ->nullable()
                    ->after('expense_category_id')
                    ->constrained('expense_subcategories')
                    ->nullOnDelete();

                $table->index('expense_subcategory_id', 'expenses_subcategory_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'expense_subcategory_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['expense_subcategory_id']);
                $table->dropIndex('expenses_subcategory_idx');
                $table->dropColumn('expense_subcategory_id');
            });
        }
    }
};


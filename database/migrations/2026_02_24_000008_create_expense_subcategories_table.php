<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['expense_category_id', 'name'], 'exp_subcat_category_name_unique');
            $table->index(['branch_id', 'expense_category_id'], 'exp_subcat_branch_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_subcategories');
    }
};

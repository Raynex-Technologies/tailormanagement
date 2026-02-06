<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create task categories table
        Schema::create('task_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('zinc');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        // Enhance todos table
        Schema::table('todos', function (Blueprint $table) {
            // Make branch_id nullable (for global admins personal tasks)
            if (Schema::hasColumn('todos', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->change();
            }

            // Add new fields
            $table->foreignId('category_id')->nullable()->after('user_id')->constrained('task_categories')->nullOnDelete();
            $table->string('priority', 20)->default('normal')->after('title');
            $table->text('note')->nullable()->after('priority');
            $table->dateTime('due_at')->nullable()->after('due_on');

            // Add index for sorting
            $table->index(['user_id', 'priority']);
            $table->index(['user_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'priority']);
            $table->dropIndex(['user_id', 'due_at']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'priority', 'note', 'due_at']);
        });

        Schema::dropIfExists('task_categories');
    }
};

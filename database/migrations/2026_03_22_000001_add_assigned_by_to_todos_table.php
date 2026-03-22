<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('todos') || Schema::hasColumn('todos', 'assigned_by')) {
            return;
        }

        Schema::table('todos', function (Blueprint $table) {
            $table->foreignId('assigned_by')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['user_id', 'assigned_by'], 'todos_user_assigned_by_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('todos') || ! Schema::hasColumn('todos', 'assigned_by')) {
            return;
        }

        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex('todos_user_assigned_by_idx');
            $table->dropConstrainedForeignId('assigned_by');
        });
    }
};

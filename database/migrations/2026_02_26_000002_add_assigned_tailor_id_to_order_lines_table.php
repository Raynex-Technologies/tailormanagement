<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_lines') || Schema::hasColumn('order_lines', 'assigned_tailor_id')) {
            return;
        }

        Schema::table('order_lines', function (Blueprint $table) {
            $table->foreignId('assigned_tailor_id')
                ->nullable()
                ->after('order_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['order_id', 'assigned_tailor_id'], 'order_lines_order_tailor_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_lines') || ! Schema::hasColumn('order_lines', 'assigned_tailor_id')) {
            return;
        }

        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropIndex('order_lines_order_tailor_idx');
            $table->dropConstrainedForeignId('assigned_tailor_id');
        });
    }
};

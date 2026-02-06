<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Add branch_id if not exists
            if (! Schema::hasColumn('conversations', 'branch_id')) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->cascadeOnDelete();
            }

            // Add direct_hash if not exists
            if (! Schema::hasColumn('conversations', 'direct_hash')) {
                $table->string('direct_hash', 64)
                    ->nullable()
                    ->after('title')
                    ->index()
                    ->comment('SHA256 hash for direct conversations: sha256(minUserId:maxUserId:branchId)');
            }

            // Add last_message_at if not exists
            if (! Schema::hasColumn('conversations', 'last_message_at')) {
                $table->timestamp('last_message_at')
                    ->nullable()
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'direct_hash')) {
                $table->dropColumn('direct_hash');
            }
            if (Schema::hasColumn('conversations', 'last_message_at')) {
                $table->dropColumn('last_message_at');
            }
            // Don't drop branch_id as it may have been added by original migration
        });
    }
};

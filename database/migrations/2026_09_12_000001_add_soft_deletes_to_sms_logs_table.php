<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Imported databases may already contain this column without the migration record.
        if (Schema::hasColumn('sms_logs', 'deleted_at')) {
            return;
        }

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

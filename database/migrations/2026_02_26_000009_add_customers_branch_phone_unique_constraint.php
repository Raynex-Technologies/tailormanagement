<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')
            || ! Schema::hasColumn('customers', 'branch_id')
            || ! Schema::hasColumn('customers', 'phone')) {
            return;
        }

        $hasDuplicates = DB::table('customers')
            ->selectRaw('branch_id, phone, COUNT(*) as aggregate')
            ->whereNotNull('branch_id')
            ->whereNotNull('phone')
            ->groupBy('branch_id', 'phone')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'Cannot enforce unique customer phone per branch: duplicate phone numbers exist within one or more branches.'
            );
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['branch_id', 'phone'], 'customers_branch_phone_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customers')
            || ! Schema::hasColumn('customers', 'branch_id')
            || ! Schema::hasColumn('customers', 'phone')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            try {
                $table->dropUnique('customers_branch_phone_unique');
            } catch (\Throwable $e) {
                // Index may not exist on partially migrated environments.
            }
        });
    }
};

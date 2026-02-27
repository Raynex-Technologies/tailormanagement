<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_items') && ! Schema::hasColumn('inventory_items', 'inventory_unit_id')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->foreignId('inventory_unit_id')
                    ->nullable()
                    ->after('inventory_category_id')
                    ->constrained('inventory_units')
                    ->nullOnDelete();
            });
        }

        $this->backfillItemUnits();
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_items') && Schema::hasColumn('inventory_items', 'inventory_unit_id')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('inventory_unit_id');
            });
        }
    }

    protected function backfillItemUnits(): void
    {
        if (! Schema::hasTable('inventory_items') || ! Schema::hasTable('inventory_units')) {
            return;
        }

        $pairs = DB::table('inventory_items')
            ->select('branch_id', 'unit')
            ->whereNotNull('branch_id')
            ->whereNotNull('unit')
            ->where('unit', '<>', '')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $unitId = DB::table('inventory_units')
                ->where('branch_id', $pair->branch_id)
                ->where('name', $pair->unit)
                ->value('id');

            if (! $unitId) {
                $unitId = DB::table('inventory_units')->insertGetId([
                    'branch_id' => $pair->branch_id,
                    'name' => $pair->unit,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('inventory_items')
                ->where('branch_id', $pair->branch_id)
                ->where('unit', $pair->unit)
                ->whereNull('inventory_unit_id')
                ->update([
                    'inventory_unit_id' => $unitId,
                ]);
        }
    }
};

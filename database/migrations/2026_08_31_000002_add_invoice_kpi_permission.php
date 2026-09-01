<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION = 'invoices.view_kpis';

    public function up(): void
    {
        $tables = config('permission.table_names');
        $guard = config('auth.defaults.guard');
        $now = now();

        DB::table($tables['permissions'])->updateOrInsert(
            ['name' => self::PERMISSION, 'guard_name' => $guard],
            ['created_at' => $now, 'updated_at' => $now],
        );
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $permissionId = DB::table($tables['permissions'])
            ->where('name', self::PERMISSION)
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table($tables['role_has_permissions'])->where('permission_id', $permissionId)->delete();
        DB::table($tables['model_has_permissions'])->where('permission_id', $permissionId)->delete();
        DB::table($tables['permissions'])->where('id', $permissionId)->delete();
    }
};

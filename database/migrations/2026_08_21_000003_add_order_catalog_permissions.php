<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'order_catalog.view',
        'order_catalog.items.manage',
        'order_catalog.packages.manage',
    ];

    private const MANAGEMENT_ROLES = ['superadmin', 'admin', 'branch_manager'];

    public function up(): void
    {
        $tables = config('permission.table_names');
        $guard = config('auth.defaults.guard');
        $now = now();

        foreach (self::PERMISSIONS as $name) {
            DB::table($tables['permissions'])->updateOrInsert(
                ['name' => $name, 'guard_name' => $guard],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $roleIds = DB::table($tables['roles'])
            ->whereIn('name', self::MANAGEMENT_ROLES)
            ->where('guard_name', $guard)
            ->pluck('id');
        $permissionIds = DB::table($tables['permissions'])
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', $guard)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table($tables['role_has_permissions'])->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $ids = DB::table($tables['permissions'])->whereIn('name', self::PERMISSIONS)->pluck('id');

        DB::table($tables['role_has_permissions'])->whereIn('permission_id', $ids)->delete();
        DB::table($tables['model_has_permissions'])->whereIn('permission_id', $ids)->delete();
        DB::table($tables['permissions'])->whereIn('id', $ids)->delete();
    }
};

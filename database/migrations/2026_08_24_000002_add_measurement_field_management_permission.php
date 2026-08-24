<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION = 'measurement_fields.manage';

    public function up(): void
    {
        $tables = config('permission.table_names');
        $guard = config('auth.defaults.guard');
        $now = now();

        DB::table($tables['permissions'])->updateOrInsert(
            ['name' => self::PERMISSION, 'guard_name' => $guard],
            ['created_at' => $now, 'updated_at' => $now],
        );

        $permissionId = DB::table($tables['permissions'])
            ->where('name', self::PERMISSION)
            ->where('guard_name', $guard)
            ->value('id');

        $roleIds = DB::table($tables['roles'])
            ->whereIn('name', ['superadmin', 'admin'])
            ->where('guard_name', $guard)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $permissionId = DB::table($tables['permissions'])->where('name', self::PERMISSION)->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table($tables['role_has_permissions'])->where('permission_id', $permissionId)->delete();
        DB::table($tables['model_has_permissions'])->where('permission_id', $permissionId)->delete();
        DB::table($tables['permissions'])->where('id', $permissionId)->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'dashboard.kpi.orders.view',
        'dashboard.kpi.revenue.view',
        'dashboard.kpi.expenses.view',
        'dashboard.kpi.item-sales.view',
        'dashboard.chart.income-expenses.view',
        'dashboard.calendar.view',
        'dashboard.order-progress.view',
        'dashboard.alert.low-stock.view',
        'dashboard.alert.purchase-requests.view',
        'dashboard.quick-actions.view',
        'dashboard.todos.view',
        'dashboard.payment-methods.view',
        'dashboard.top-customers.view',
    ];

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

        $parentId = DB::table($tables['permissions'])
            ->where('name', 'dashboard.view')
            ->where('guard_name', $guard)
            ->value('id');

        if (! $parentId) {
            return;
        }

        $roleIds = DB::table($tables['role_has_permissions'])
            ->where('permission_id', $parentId)
            ->pluck('role_id');
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
        $ids = DB::table($tables['permissions'])
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        DB::table($tables['role_has_permissions'])->whereIn('permission_id', $ids)->delete();
        DB::table($tables['model_has_permissions'])->whereIn('permission_id', $ids)->delete();
        DB::table($tables['permissions'])->whereIn('id', $ids)->delete();
    }
};

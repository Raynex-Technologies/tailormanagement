<?php

namespace App\Support;

/**
 * Groups permissions by module for display in role create/edit forms.
 * Keys are display labels; values are permission name prefixes or exact names.
 */
class PermissionGroups
{
    /**
     * Module label => array of permission names (exact match) or prefixes (.*).
     * Order defines display order. Permissions are matched by prefix then exact.
     */
    protected static array $groups = [
        'Dashboard' => [
            'dashboard.view',
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
        ],
        'Users & Access Control' => [
            'users.view',
            'users.manage',
            'roles.manage',
            'settings.system-ui.view',
            'settings.system-ui.update',
            'branches.view',
            'branches.manage',
        ],
        'Orders' => [
            'orders.view',
            'order_catalog.view',
            'order_catalog.items.manage',
            'order_catalog.packages.manage',
            'measurement_fields.manage',
            'orders.create',
            'orders.update',
            'orders.assign_tailor',
            'orders.change_status',
            'orders.mark_completed',
            'orders.view_financials',
            'orders.materials.view',
            'orders.materials.manage',
            'delivery_notes.create',
            'delivery_notes.view',
        ],
        'Stock Requests' => [
            'stock_requests.view',
            'stock_requests.create',
            'stock_requests.review',
            'stock_requests.fulfill',
        ],
        'Payments' => [
            'payments.view',
            'payments.create',
            'payments.refund',
        ],
        'Point of Sale' => [
            'pos.view',
            'pos.sell',
            'sales.view.own',
            'sales.view.branch',
            'sales.view.all',
        ],
        'Inventory' => [
            'inventory.view',
            'inventory.items.manage',
            'inventory.stock.receive',
            'inventory.stock.adjust',
            'inventory.issue',
        ],
        'Capital' => [
            'capital.view',
            'capital.assign',
            'capital.close',
        ],
        'Procurement' => [
            'procurement.view',
            'procurement.request.create',
            'procurement.request.submit',
            'procurement.request.review',
            'procurement.request.approve',
            'procurement.request.decline',
            'procurement.po.manage',
            'procurement.receive',
        ],
        'Expenses' => [
            'expenses.view',
            'expenses.manage',
            'expenses.categories.manage',
        ],
        'Reports' => [
            'reports.view',
            'reports.export',
        ],
        'Installments' => [
            'installments.view',
            'installments.manage',
            'installments.packages.manage',
            'installments.payments.record',
            'installments.analytics.view',
        ],
        'Messaging' => [
            'messages.use',
            'messages.group.create',
            'messages.any_branch',
        ],
        'SMS' => [
            'sms.send',
            'sms.logs.view',
            'sms.templates.manage',
            'sms-settings.view',
            'sms-settings.update',
            'sms-templates.view',
            'sms-templates.update',
        ],
        'Todos' => [
            'todos.use',
            'todos.assign',
        ],
        'Online Bookings' => [
            'online-bookings.view',
            'online-bookings.manage',
            'online-bookings.review',
            'online-bookings.convert',
            'online-bookings.delete',
            'appointments.view',
            'appointments.manage',
            'appointments.approve',
            'appointments.decline',
            'appointments.reschedule',
            'appointments.cancel',
            'appointments.complete',
            'availability.view',
            'availability.manage',
            'garment-options.view',
            'garment-options.manage',
        ],
        'Storefront' => [
            'storefront.view',
            'storefront.settings.manage',
            'storefront.catalog.manage',
            'storefront.cms.manage',
            'storefront.shipping.manage',
            'storefront.orders.manage',
            'storefront.payments.manage',
        ],
    ];

    /**
     * Group a collection of permission models by module.
     * Returns [ 'Module Label' => [Permission, ...], ... ].
     *
     * @param  \Illuminate\Support\Collection<int, \Spatie\Permission\Models\Permission>  $permissions
     * @return array<string, \Illuminate\Support\Collection<int, \Spatie\Permission\Models\Permission>>
     */
    public static function groupPermissions($permissions): array
    {
        $permissionNames = $permissions->pluck('name')->flip()->all();
        $grouped = [];

        foreach (static::$groups as $label => $names) {
            $grouped[$label] = $permissions->filter(function ($permission) use ($names) {
                return in_array($permission->name, $names, true);
            })->values();
        }

        // Any permission not in a group goes to "Other"
        $assigned = collect($grouped)->flatten()->pluck('id')->unique();
        $other = $permissions->filter(fn ($p) => ! $assigned->contains($p->id))->values();
        if ($other->isNotEmpty()) {
            $grouped['Other'] = $other;
        }

        return $grouped;
    }

    /**
     * Get all known permission names from groups (for validation or sync).
     *
     * @return array<string>
     */
    public static function allPermissionNames(): array
    {
        $names = [];
        foreach (static::$groups as $list) {
            foreach ($list as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }
}

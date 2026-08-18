<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ============================================================================
 * CANONICAL ROLE-PERMISSION MAPPING (SOURCE OF TRUTH)
 * ============================================================================
 *
 * This seeder defines the EXACT permissions each role should have.
 * Running this seeder will REMOVE any permissions not explicitly listed.
 *
 * ┌────────────────────┬───────┬───────┬─────────┬──────────┬───────────┬────────┬───────┐
 * │ Permission         │ Super │ Admin │ Branch  │ Account- │ Store-    │ Tailor │ Sales │
 * │                    │ admin │       │ Manager │ ant      │ keeper    │        │       │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ CORE               │       │       │         │          │           │        │       │
 * │ dashboard.view     │   ✓   │   ✓   │    ✓    │    ✓     │     ✓     │   ✓    │   ✓   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ USERS & ACCESS     │       │       │         │          │           │        │       │
 * │ users.view         │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ users.manage       │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ roles.manage       │   ✓   │   ✓   │    -    │    -     │     -     │   -    │   -   │
 * │ branches.view      │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ branches.manage    │   ✓   │   ✓   │    -    │    -     │     -     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ ORDERS             │       │       │         │          │           │        │       │
 * │ orders.view        │   ✓   │   ✓   │    ✓    │    ✓     │     ✓     │   ✓    │   ✓   │
 * │ orders.create      │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ orders.update      │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ orders.assign_tail │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ orders.change_stat │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ orders.mark_compl  │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ orders.view_financ │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ orders.materials.v │   ✓   │   ✓   │    ✓    │    -     │     ✓     │   -    │   -   │
 * │ orders.materials.m │   ✓   │   ✓   │    ✓    │    -     │     ✓     │   -    │   -   │
 * │ delivery_notes.cre │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ delivery_notes.vie │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ STOCK REQUESTS     │       │       │         │          │           │        │       │
 * │ stock_requests.vie │   ✓   │   ✓   │    ✓    │    ✓     │     ✓     │   -    │   -   │
 * │ stock_requests.cre │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ stock_requests.rev │   ✓   │   ✓   │    ✓    │    -     │     ✓     │   -    │   -   │
 * │ stock_requests.ful │   ✓   │   ✓   │    ✓    │    -     │     ✓     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ PAYMENTS           │       │       │         │          │           │        │       │
 * │ payments.view      │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ payments.create    │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ payments.refund    │   ✓   │   ✓   │    -    │    -     │     -     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ INVENTORY          │       │       │         │          │           │        │       │
 * │ inventory.view     │   ✓   │   ✓   │    ✓    │    ✓     │     ✓     │   -    │   -   │
 * │ inventory.items.ma │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ inventory.stock.re │   ✓   │   ✓   │    ✓    │    -     │     ✓     │   -    │   -   │
 * │ inventory.stock.ad │   ✓   │   ✓   │    ✓    │    -     │     ✓     │   -    │   -   │
 * │ inventory.issue    │   ✓   │   ✓   │    ✓    │    -     │     ✓     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ CAPITAL            │       │       │         │          │           │        │       │
 * │ capital.view       │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ capital.assign     │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ capital.close      │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ PROCUREMENT        │       │       │         │          │           │        │       │
 * │ procurement.view   │   ✓   │   ✓   │    ✓    │    ✓     │     ✓     │   -    │   -   │
 * │ procurement.req.cr │   ✓   │   ✓   │    ✓    │    -     │     ✓     │   -    │   -   │
 * │ procurement.req.su │   ✓   │   ✓   │    ✓    │    -     │     ✓     │   -    │   -   │
 * │ procurement.req.re │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ procurement.req.ap │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ procurement.req.de │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ procurement.po.man │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ procurement.receiv │   ✓   │   ✓   │    ✓    │    ✓     │     ✓     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ EXPENSES           │       │       │         │          │           │        │       │
 * │ expenses.view      │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ expenses.manage    │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ expenses.cat.manag │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ REPORTS            │       │       │         │          │           │        │       │
 * │ reports.view       │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ reports.export     │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ MESSAGING          │       │       │         │          │           │        │       │
 * │ messages.use       │   ✓   │   ✓   │    ✓    │    ✓     │     ✓     │   ✓    │   ✓   │
 * │ messages.group.cre │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ messages.any_branc │   ✓   │   -   │    -    │    -     │     -     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ SMS                │       │       │         │          │           │        │       │
 * │ sms.send           │   ✓   │   ✓   │    ✓    │    -     │     -     │   -    │   -   │
 * │ sms.logs.view      │   ✓   │   ✓   │    ✓    │    ✓     │     -     │   -    │   -   │
 * │ sms.templates.mana │   ✓   │   ✓   │    -    │    -     │     -     │   -    │   -   │
 * ├────────────────────┼───────┼───────┼─────────┼──────────┼───────────┼────────┼───────┤
 * │ TODOS              │       │       │         │          │           │        │       │
 * │ todos.use          │   ✓   │   ✓   │    ✓    │    ✓     │     ✓     │   ✓    │   ✓   │
 * └────────────────────┴───────┴───────┴─────────┴──────────┴───────────┴────────┴───────┘
 *
 * IMPORTANT NOTES:
 * ================
 * - STOREKEEPER: NO access to financials, payments, capital, reports, or order pricing
 * - TAILOR: Minimal access - only assigned orders (enforced by policy) + messaging + todos
 * - SALES: Minimal access - view orders + messaging + todos (no order creation per strict spec)
 * - ACCOUNTANT: Financial focus - payments, capital view, expenses, reports
 * - syncPermissions() removes any permissions NOT in the list (cleanup)
 *
 * To run: php artisan db:seed --class=RolesAndPermissionsSeeder
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ====================================================================
        // STEP 1: Define ALL permissions (create if not exists)
        // ====================================================================
        $dashboardPermissions = [
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

        $allPermissions = [
            // Core
            'dashboard.view',
            ...$dashboardPermissions,

            // Users & Access Control
            'users.view',
            'users.manage',
            'roles.manage',
            'settings.system-ui.view',
            'settings.system-ui.update',

            // Branch management
            'branches.view',
            'branches.manage',

            // Orders
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.assign_tailor',
            'orders.change_status',
            'orders.mark_completed',
            'orders.view_financials',
            'orders.materials.view',
            'orders.materials.manage',

            // Delivery Notes
            'delivery_notes.create',
            'delivery_notes.view',

            // Stock Requests
            'stock_requests.view',
            'stock_requests.create',
            'stock_requests.review',
            'stock_requests.fulfill',

            // Payments
            'payments.view',
            'payments.create',
            'payments.refund',

            // Point of Sale
            'pos.view',
            'pos.sell',
            'sales.view.own',
            'sales.view.branch',
            'sales.view.all',

            // Inventory
            'inventory.view',
            'inventory.items.manage',
            'inventory.stock.receive',
            'inventory.stock.adjust',
            'inventory.issue',

            // Capital
            'capital.view',
            'capital.assign',
            'capital.close',

            // Procurement
            'procurement.view',
            'procurement.request.create',
            'procurement.request.submit',
            'procurement.request.review',
            'procurement.request.approve',
            'procurement.request.decline',
            'procurement.po.manage',
            'procurement.receive',

            // Expenses
            'expenses.view',
            'expenses.manage',
            'expenses.categories.manage',

            // Reports
            'reports.view',
            'reports.export',

            // Installments
            'installments.view',
            'installments.manage',
            'installments.packages.manage',
            'installments.payments.record',
            'installments.analytics.view',

            // Messaging
            'messages.use',
            'messages.group.create',
            'messages.any_branch',

            // Todos
            'todos.use',
            'todos.assign',

            // Online bookings and appointments
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

            // Storefront
            'storefront.view',
            'storefront.settings.manage',
            'storefront.catalog.manage',
            'storefront.cms.manage',
            'storefront.shipping.manage',
            'storefront.orders.manage',
            'storefront.payments.manage',

            // SMS
            'sms.send',
            'sms.logs.view',
            'sms.templates.manage',
            'sms-settings.view',
            'sms-settings.update',
            'sms-templates.view',
            'sms-templates.update',
        ];

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->command->info('✓ Created/verified '.count($allPermissions).' permissions');

        // ====================================================================
        // STEP 2: Define STRICT role-permission mappings
        // ====================================================================
        $rolePermissions = [
            // -----------------------------------------------------------------
            // SUPERADMIN: All permissions
            // -----------------------------------------------------------------
            'superadmin' => $allPermissions,

            // -----------------------------------------------------------------
            // ADMIN: All permissions except superadmin-only (messages.any_branch)
            // -----------------------------------------------------------------
            'admin' => [
                'dashboard.view',
                // Users & Access
                'users.view',
                'users.manage',
                'roles.manage',
                'settings.system-ui.view',
                'settings.system-ui.update',
                'branches.view',
                'branches.manage',
                // Orders - FULL ACCESS
                'orders.view',
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
                // Stock Requests - FULL ACCESS
                'stock_requests.view',
                'stock_requests.create',
                'stock_requests.review',
                'stock_requests.fulfill',
                // Payments - FULL ACCESS
                'payments.view',
                'payments.create',
                'payments.refund',
                // Point of Sale
                'pos.view',
                'pos.sell',
                'sales.view.branch',
                // Inventory - FULL ACCESS
                'inventory.view',
                'inventory.items.manage',
                'inventory.stock.receive',
                'inventory.stock.adjust',
                'inventory.issue',
                // Capital - FULL ACCESS
                'capital.view',
                'capital.assign',
                'capital.close',
                // Procurement - FULL ACCESS
                'procurement.view',
                'procurement.request.create',
                'procurement.request.submit',
                'procurement.request.review',
                'procurement.request.approve',
                'procurement.request.decline',
                'procurement.po.manage',
                'procurement.receive',
                // Expenses - FULL ACCESS
                'expenses.view',
                'expenses.manage',
                'expenses.categories.manage',
                // Reports - FULL ACCESS
                'reports.view',
                'reports.export',
                // Installments - FULL ACCESS
                'installments.view',
                'installments.manage',
                'installments.packages.manage',
                'installments.payments.record',
                'installments.analytics.view',
                // Messaging
                'messages.use',
                'messages.group.create',
                // Todos
                'todos.use',
                'todos.assign',
                // Online bookings and appointments
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
                // Storefront
                'storefront.view',
                'storefront.settings.manage',
                'storefront.catalog.manage',
                'storefront.cms.manage',
                'storefront.shipping.manage',
                'storefront.orders.manage',
                'storefront.payments.manage',
                // SMS - FULL ACCESS
                'sms.send',
                'sms.logs.view',
                'sms.templates.manage',
                'sms-settings.view',
                'sms-settings.update',
                'sms-templates.view',
                'sms-templates.update',
            ],

            // -----------------------------------------------------------------
            // BRANCH_MANAGER: Same as admin but branch-scoped (by architecture)
            // Slightly fewer permissions than admin (no roles.manage, branches.manage)
            // -----------------------------------------------------------------
            'branch_manager' => [
                'dashboard.view',
                // Users & Access (within branch)
                'users.view',
                'users.manage',
                'branches.view',
                // Orders - FULL ACCESS (within branch)
                'orders.view',
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
                // Stock Requests - FULL ACCESS
                'stock_requests.view',
                'stock_requests.create',
                'stock_requests.review',
                'stock_requests.fulfill',
                // Payments - View & Create (no refund)
                'payments.view',
                'payments.create',
                // Point of Sale
                'pos.view',
                'pos.sell',
                'sales.view.branch',
                // Inventory - FULL ACCESS
                'inventory.view',
                'inventory.items.manage',
                'inventory.stock.receive',
                'inventory.stock.adjust',
                'inventory.issue',
                // Capital - FULL ACCESS (within branch)
                'capital.view',
                'capital.assign',
                'capital.close',
                // Procurement - FULL ACCESS
                'procurement.view',
                'procurement.request.create',
                'procurement.request.submit',
                'procurement.request.review',
                'procurement.request.approve',
                'procurement.request.decline',
                'procurement.po.manage',
                'procurement.receive',
                // Expenses - FULL ACCESS
                'expenses.view',
                'expenses.manage',
                'expenses.categories.manage',
                // Reports - FULL ACCESS
                'reports.view',
                'reports.export',
                // Installments - FULL ACCESS
                'installments.view',
                'installments.manage',
                'installments.packages.manage',
                'installments.payments.record',
                'installments.analytics.view',
                // Messaging
                'messages.use',
                'messages.group.create',
                // Todos
                'todos.use',
                'todos.assign',
                // Online bookings and appointments
                'online-bookings.view',
                'online-bookings.manage',
                'online-bookings.review',
                'online-bookings.convert',
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
                // Storefront
                'storefront.view',
                'storefront.settings.manage',
                'storefront.catalog.manage',
                'storefront.cms.manage',
                'storefront.shipping.manage',
                'storefront.orders.manage',
                'storefront.payments.manage',
                // SMS
                'sms.send',
                'sms.logs.view',
            ],

            // -----------------------------------------------------------------
            // ACCOUNTANT: Financial focus
            // CAN: View orders + financials, payments, capital view, expenses, reports
            // CANNOT: Create/update orders, manage inventory, manage capital
            // -----------------------------------------------------------------
            'accountant' => [
                'dashboard.view',
                // Orders - VIEW ONLY + FINANCIALS
                'orders.view',
                'orders.view_financials',
                'delivery_notes.view',
                // Stock Requests - VIEW ONLY
                'stock_requests.view',
                // Payments - View & Create
                'payments.view',
                'payments.create',
                // Point of Sale
                'pos.view',
                'pos.sell',
                'sales.view.own',
                // Inventory - VIEW ONLY
                'inventory.view',
                // Capital - VIEW ONLY (admin assigns allocations)
                'capital.view',
                // Procurement - Review/Approve/PO management
                'procurement.view',
                'procurement.request.review',
                'procurement.request.approve',
                'procurement.request.decline',
                'procurement.po.manage',
                'procurement.receive',
                // Expenses - FULL ACCESS
                'expenses.view',
                'expenses.manage',
                'expenses.categories.manage',
                // Reports - FULL ACCESS
                'reports.view',
                'reports.export',
                // Installments - Finance oversight
                'installments.view',
                'installments.payments.record',
                'installments.analytics.view',
                // Messaging
                'messages.use',
                // Todos
                'todos.use',
                // SMS - logs only
                'sms.logs.view',
            ],

            // -----------------------------------------------------------------
            // STOREKEEPER: Inventory & Materials focus (STRICT)
            // CAN: Manage inventory, stock requests, materials, procurement receiving
            // CANNOT: Financials, payments, capital, reports, order pricing
            // -----------------------------------------------------------------
            'storekeeper' => [
                'dashboard.view',
                // Orders - VIEW (no financials) + MATERIALS ONLY
                'orders.view',
                'orders.materials.view',
                'orders.materials.manage',
                // Stock Requests - Core storekeeper function
                'stock_requests.view',
                'stock_requests.review',
                'stock_requests.fulfill',
                // Inventory - Operational access (no items.manage)
                'inventory.view',
                'inventory.stock.receive',
                'inventory.stock.adjust',
                'inventory.issue',
                // Procurement - Create requests & receive goods
                'procurement.view',
                'procurement.request.create',
                'procurement.request.submit',
                'procurement.receive',
                // Messaging
                'messages.use',
                // Todos
                'todos.use',
                // ============================================================
                // EXPLICITLY NOT INCLUDED (storekeeper MUST NOT have):
                // - orders.view_financials
                // - orders.create, orders.update
                // - payments.*
                // - capital.*
                // - reports.*
                // - expenses.*
                // - inventory.items.manage
                // - sms.*
                // ============================================================
            ],

            // -----------------------------------------------------------------
            // TAILOR: Minimal access - assigned orders only
            // CAN: View assigned orders, messaging, todos
            // CANNOT: Create/update orders, inventory, payments, reports
            // -----------------------------------------------------------------
            'tailor' => [
                'dashboard.view',
                // Orders - VIEW ONLY (assigned orders enforced by OrderPolicy)
                'orders.view',
                // Messaging
                'messages.use',
                // Todos
                'todos.use',
                // ============================================================
                // EXPLICITLY NOT INCLUDED:
                // - stock_requests.* (tailors request via order UI, not directly)
                // - inventory.*
                // - payments.*
                // - reports.*
                // ============================================================
            ],

            // -----------------------------------------------------------------
            // SALES: Customer-facing minimal access
            // CAN: View orders, messaging, todos
            // CANNOT: Create/update orders (per strict spec), inventory, payments
            // -----------------------------------------------------------------
            'sales' => [
                'dashboard.view',
                // Installments - sales can create and collect installment sales
                'installments.view',
                'installments.manage',
                'installments.payments.record',
                // Orders - VIEW ONLY
                'orders.view',
                // Online booking intake
                'online-bookings.view',
                'appointments.view',
                'availability.view',
                // Point of Sale
                'pos.view',
                'pos.sell',
                // Messaging
                'messages.use',
                // Todos
                'todos.use',
                // ============================================================
                // EXPLICITLY NOT INCLUDED (per strict specification):
                // - orders.create, orders.update, orders.mark_completed
                // - delivery_notes.*
                // - stock_requests.*
                // - payments.*
                // - inventory.*
                // - reports.*
                // - sms.*
                // ============================================================
            ],

            // -----------------------------------------------------------------
            // CUSTOMER: storefront portal access only
            // -----------------------------------------------------------------
            'customer' => [
            ],
        ];

        foreach ($rolePermissions as &$permissions) {
            if (in_array('dashboard.view', $permissions, true)) {
                $permissions = array_values(array_unique([...$permissions, ...$dashboardPermissions]));
            }
        }
        unset($permissions);

        // ====================================================================
        // STEP 3: Apply permissions to roles (syncPermissions cleans up extras)
        // ====================================================================
        $summary = [];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($permissions);
            $summary[$roleName] = count($permissions);
        }

        // ====================================================================
        // STEP 4: Output summary
        // ====================================================================
        $this->command->info('');
        $this->command->info('====== ROLE-PERMISSION SUMMARY ======');
        foreach ($summary as $role => $count) {
            $this->command->info(sprintf('  %-16s => %d permissions', $role, $count));
        }
        $this->command->info('=====================================');

        // ====================================================================
        // STEP 5: Verification checks
        // ====================================================================
        $this->command->info('');
        $this->command->info('====== VERIFICATION CHECKS ======');

        // Check storekeeper does NOT have forbidden permissions
        $storekeeper = Role::findByName('storekeeper');
        $forbiddenForStorekeeper = [
            'orders.view_financials',
            'payments.view',
            'payments.create',
            'capital.view',
            'capital.assign',
            'capital.close',
            'reports.view',
            'reports.export',
            'inventory.items.manage',
        ];

        $storekeeperIssues = [];
        foreach ($forbiddenForStorekeeper as $perm) {
            if ($storekeeper->hasPermissionTo($perm)) {
                $storekeeperIssues[] = $perm;
            }
        }

        if (empty($storekeeperIssues)) {
            $this->command->info('✓ Storekeeper: CLEAN (no forbidden permissions)');
        } else {
            $this->command->error('✗ Storekeeper has forbidden permissions: '.implode(', ', $storekeeperIssues));
        }

        // Verify accountant has expected permissions
        $accountant = Role::findByName('accountant');
        $accountantRequired = ['orders.view_financials', 'payments.view', 'payments.create', 'reports.view'];
        $accountantMissing = [];
        foreach ($accountantRequired as $perm) {
            if (! $accountant->hasPermissionTo($perm)) {
                $accountantMissing[] = $perm;
            }
        }

        if (empty($accountantMissing)) {
            $this->command->info('✓ Accountant: OK (has required financial permissions)');
        } else {
            $this->command->error('✗ Accountant missing: '.implode(', ', $accountantMissing));
        }

        // Verify branch_manager has expected permissions
        $branchManager = Role::findByName('branch_manager');
        $branchManagerRequired = ['orders.create', 'payments.create', 'capital.assign', 'todos.assign', 'storefront.settings.manage'];
        $branchManagerMissing = [];
        foreach ($branchManagerRequired as $perm) {
            if (! $branchManager->hasPermissionTo($perm)) {
                $branchManagerMissing[] = $perm;
            }
        }

        if (empty($branchManagerMissing)) {
            $this->command->info('✓ Branch Manager: OK (has required admin permissions)');
        } else {
            $this->command->error('✗ Branch Manager missing: '.implode(', ', $branchManagerMissing));
        }

        $this->command->info('=================================');
        $this->command->info('');
        $this->command->info('Permission cleanup complete. All roles now have ONLY their intended permissions.');
    }
}

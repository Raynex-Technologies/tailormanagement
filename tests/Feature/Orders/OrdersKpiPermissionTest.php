<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Livewire\Roles\Form as RoleForm;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersKpiPermissionTest extends TestCase
{
    public function test_permitted_user_sees_order_kpis(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $user->givePermissionTo('orders.view_kpis');

        Livewire::test(OrdersIndex::class)
            ->assertSee('Order overview')
            ->assertViewHas('canViewKpis', true);
    }

    public function test_user_with_orders_access_but_without_kpi_permission_does_not_see_them(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        Livewire::test(OrdersIndex::class)
            ->assertDontSee('Order overview')
            ->assertViewHas('canViewKpis', false)
            ->assertViewHas('kpis', []);
    }

    public function test_orders_list_and_filters_remain_usable_without_kpi_permission(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'KPI Restricted Customer',
        ]);
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'subtotal' => 45000,
            'discount' => 0,
            'total' => 45000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        Livewire::test(OrdersIndex::class)
            ->assertSee($order->order_no)
            ->set('search', 'KPI Restricted Customer')
            ->assertSee($order->order_no)
            ->set('statusFilter', OrderStatus::Completed->value)
            ->assertDontSee($order->order_no)
            ->set('statusFilter', '')
            ->assertSee($order->order_no);
    }

    public function test_kpi_aggregation_is_not_executed_for_unauthorized_user(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $component = Livewire::test(OrdersIndex::class);

        $component->assertViewHas('kpis', []);
        $this->assertFalse(collect($queries)->contains(
            fn (string $query): bool => str_contains($query, 'payment_totals')
                || str_contains($query, 'payments_total')
                || str_contains($query, 'orders_count')
        ));
    }

    public function test_order_kpi_permission_is_available_in_role_management(): void
    {
        $this->actingAsRole('admin', $this->branch);

        Livewire::test(RoleForm::class)
            ->assertSee('View Order KPIs')
            ->assertDontSee('orders.view_kpis');
    }
}

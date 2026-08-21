<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrdersIndexFiltersTest extends TestCase
{
    public function test_orders_index_does_not_expose_payments_link_for_legacy_create_only_permission(): void
    {
        Permission::firstOrCreate(['name' => 'payment.create', 'guard_name' => 'web']);

        $role = Role::create(['name' => 'legacy_payment_creator', 'guard_name' => 'web']);
        $role->givePermissionTo(['orders.view', 'payment.create']);

        $user = $this->createUserWithRole('legacy_payment_creator', $this->branch);
        $this->actingAs($user);

        Livewire::test(OrdersIndex::class)
            ->assertSee('Orders Management')
            ->assertDontSee('Record and view payments related to orders.')
            ->assertDontSee('View Payments');
    }

    public function test_orders_kpis_default_to_current_month_and_include_payments_and_growth(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $current = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->startOfMonth()->addDay()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 120000,
            'discount' => 0,
            'total' => 120000,
            'payment_status' => PaymentStatus::Partial,
            'created_by' => $user->id,
        ]);

        $previous = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->subMonthNoOverflow()->startOfMonth()->addDay()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 60000,
            'discount' => 0,
            'total' => 60000,
            'payment_status' => PaymentStatus::Partial,
            'created_by' => $user->id,
        ]);

        OrderPayment::create(['branch_id' => $this->branch->id, 'order_id' => $current->id, 'amount' => 50000, 'received_by' => $user->id]);
        OrderPayment::create(['branch_id' => $this->branch->id, 'order_id' => $previous->id, 'amount' => 25000, 'received_by' => $user->id]);

        $this->assertSame(50000.0, (float) OrderPayment::query()->where('order_id', $current->id)->sum('amount'));

        $component = Livewire::test(OrdersIndex::class)
            ->assertSet('dateFrom', now()->startOfMonth()->toDateString())
            ->assertSet('dateTo', now()->endOfMonth()->toDateString())
            ->assertSee('Tsh 120K')
            ->assertSee('Tsh 120,000')
            ->assertSee('Tsh 50K')
            ->assertSee('Tsh 70K')
            ->assertSee('100.0%');

        $this->assertSame(50000.0, (float) $component->viewData('kpis')['paid']['value']);
    }

    public function test_orders_management_sorts_by_order_date_desc(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $olderOrderDate = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 10000,
            'discount' => 0,
            'total' => 10000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $newerOrderDate = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 12000,
            'discount' => 0,
            'total' => 12000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        // Make created_at misleading to verify ordering uses order_date.
        DB::table('orders')->where('id', $olderOrderDate->id)->update([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->where('id', $newerOrderDate->id)->update([
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);

        Livewire::test(OrdersIndex::class)
            ->assertSeeInOrder([$newerOrderDate->order_no, $olderOrderDate->order_no]);
    }

    public function test_orders_management_date_filter_uses_order_date(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $currentMonthOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 15000,
            'discount' => 0,
            'total' => 15000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $lastMonthOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->subMonth()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 16000,
            'discount' => 0,
            'total' => 16000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        // Make created_at misleading to verify filter uses order_date.
        DB::table('orders')->where('id', $currentMonthOrder->id)->update([
            'created_at' => now()->subMonths(6),
            'updated_at' => now()->subMonths(6),
        ]);

        DB::table('orders')->where('id', $lastMonthOrder->id)->update([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(OrdersIndex::class)
            ->set('dateFrom', now()->startOfMonth()->toDateString())
            ->set('dateTo', now()->endOfMonth()->toDateString())
            ->assertSee($currentMonthOrder->order_no)
            ->assertDontSee($lastMonthOrder->order_no);
    }
}

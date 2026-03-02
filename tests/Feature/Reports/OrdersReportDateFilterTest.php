<?php

namespace Tests\Feature\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Reports\OrdersReport;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrdersReportDateFilterTest extends TestCase
{
    public function test_orders_report_page_uses_live_filter_bindings(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        $this->get(route('reports.orders'))
            ->assertOk()
            ->assertSee('wire:model.live="dateFrom"', false)
            ->assertSee('wire:model.live="dateTo"', false)
            ->assertSee('wire:model.live="status"', false)
            ->assertSee('wire:model.live="paymentStatus"', false)
            ->assertSee('wire:model.live="tailorId"', false)
            ->assertSee('wire:model.live.debounce.300ms="search"', false);
    }

    public function test_orders_report_filters_by_order_date_not_created_at(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $inRangeOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->toDateString(),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $outOfRangeOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->subMonth()->toDateString(),
            'subtotal' => 60000,
            'discount' => 0,
            'total' => 60000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        // Make created_at misleading to prove the report uses order_date.
        DB::table('orders')->where('id', $inRangeOrder->id)->update([
            'created_at' => now()->subMonths(6),
            'updated_at' => now()->subMonths(6),
        ]);

        DB::table('orders')->where('id', $outOfRangeOrder->id)->update([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = new OrdersReport([
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $orderIds = $report->rows(50)->getCollection()->pluck('id')->all();

        $this->assertContains($inRangeOrder->id, $orderIds);
        $this->assertNotContains($outOfRangeOrder->id, $orderIds);
    }

    public function test_orders_report_falls_back_to_created_at_when_order_date_missing(): void
    {
        $user = $this->actingAsRole('branch_manager', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $legacyOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => null,
            'subtotal' => 45000,
            'discount' => 0,
            'total' => 45000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $user->id,
        ]);

        $legacyCreatedAt = now()->subDays(2)->startOfDay();
        DB::table('orders')->where('id', $legacyOrder->id)->update([
            'created_at' => $legacyCreatedAt,
            'updated_at' => $legacyCreatedAt,
        ]);

        $report = new OrdersReport([
            'date_from' => $legacyCreatedAt->toDateString(),
            'date_to' => $legacyCreatedAt->toDateString(),
        ]);

        $orderIds = $report->rows(50)->getCollection()->pluck('id')->all();

        $this->assertContains($legacyOrder->id, $orderIds);
    }
}

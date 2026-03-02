<?php

namespace Tests\Feature\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Reports\SalesReport;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesReportDateFilterTest extends TestCase
{
    public function test_sales_report_page_uses_live_filter_bindings(): void
    {
        $this->actingAsRole('accountant', $this->branch);

        $this->get(route('reports.sales'))
            ->assertOk()
            ->assertSee('wire:model.live="dateFrom"', false)
            ->assertSee('wire:model.live="dateTo"', false)
            ->assertSee('wire:model.live="method"', false)
            ->assertSee('wire:model.live.debounce.300ms="search"', false);
    }

    public function test_sales_report_filters_by_order_date_not_payment_date(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $inRangeOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->toDateString(),
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_status' => PaymentStatus::Partial,
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
            'payment_status' => PaymentStatus::Partial,
            'created_by' => $user->id,
        ]);

        // Intentionally mismatch payment dates to prove filter uses order_date.
        OrderPayment::create([
            'branch_id' => $this->branch->id,
            'order_id' => $inRangeOrder->id,
            'amount' => 20000,
            'payment_method_id' => null,
            'paid_at' => now()->subMonths(6),
            'received_by' => $user->id,
        ]);

        OrderPayment::create([
            'branch_id' => $this->branch->id,
            'order_id' => $outOfRangeOrder->id,
            'amount' => 25000,
            'payment_method_id' => null,
            'paid_at' => now(),
            'received_by' => $user->id,
        ]);

        $report = new SalesReport([
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $orderIds = $report->rows(50)->getCollection()->pluck('order_id')->all();

        $this->assertContains($inRangeOrder->id, $orderIds);
        $this->assertNotContains($outOfRangeOrder->id, $orderIds);
    }

    public function test_sales_report_falls_back_to_order_created_at_when_order_date_missing(): void
    {
        $user = $this->actingAsRole('accountant', $this->branch);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $legacyOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => null,
            'subtotal' => 45000,
            'discount' => 0,
            'total' => 45000,
            'payment_status' => PaymentStatus::Partial,
            'created_by' => $user->id,
        ]);

        $legacyCreatedAt = now()->subDays(2)->startOfDay();
        DB::table('orders')->where('id', $legacyOrder->id)->update([
            'created_at' => $legacyCreatedAt,
            'updated_at' => $legacyCreatedAt,
        ]);

        OrderPayment::create([
            'branch_id' => $this->branch->id,
            'order_id' => $legacyOrder->id,
            'amount' => 10000,
            'payment_method_id' => null,
            'paid_at' => now(),
            'received_by' => $user->id,
        ]);

        $report = new SalesReport([
            'date_from' => $legacyCreatedAt->toDateString(),
            'date_to' => $legacyCreatedAt->toDateString(),
        ]);

        $orderIds = $report->rows(50)->getCollection()->pluck('order_id')->all();

        $this->assertContains($legacyOrder->id, $orderIds);
    }
}

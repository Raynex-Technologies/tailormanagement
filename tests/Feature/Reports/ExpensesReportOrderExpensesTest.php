<?php

namespace Tests\Feature\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\OrderExpense;
use App\Reports\ExpensesReport;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpensesReportOrderExpensesTest extends TestCase
{
    public function test_summary_and_rows_include_order_expenses(): void
    {
        $accountant = $this->actingAsRole('accountant', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);

        $category = ExpenseCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Utilities',
        ]);

        Expense::create([
            'branch_id' => $this->branch->id,
            'expense_category_id' => $category->id,
            'amount' => 12000,
            'vendor' => 'Power Utility',
            'reference' => 'EXP-1001',
            'expense_date' => now()->toDateString(),
            'created_by' => $accountant->id,
            'note' => 'Electricity bill',
        ]);

        $order = $this->createOrderForBranch($accountant->id);
        $orderExpense = OrderExpense::create([
            'order_id' => $order->id,
            'tailor_id' => $tailor->id,
            'amount' => 3000,
            'notes' => 'Embroidery thread',
        ]);
        $this->setOrderExpenseTimestamp($orderExpense->id, now());

        $report = new ExpensesReport([
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $summary = $report->summary();

        $this->assertSame(2, $summary['total_count']);
        $this->assertEquals(15000.0, $summary['total_expenses']);

        $rows = $report->rows(20)->getCollection();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->contains(fn ($row) => $row->source_type === 'expense'));
        $this->assertTrue($rows->contains(fn ($row) => $row->source_type === 'order_expense'));
        $this->assertContains('Order Expenses', $report->categoryBreakdown()->pluck('category_name')->all());
    }

    public function test_order_expenses_respect_date_category_and_search_filters(): void
    {
        $accountant = $this->actingAsRole('accountant', $this->branch);
        $tailor = $this->createUserWithRole('tailor', $this->branch);

        $category = ExpenseCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Operations',
        ]);

        Expense::create([
            'branch_id' => $this->branch->id,
            'expense_category_id' => $category->id,
            'amount' => 8000,
            'vendor' => 'Stationery Shop',
            'reference' => 'EXP-2001',
            'expense_date' => now()->toDateString(),
            'created_by' => $accountant->id,
            'note' => 'Paper purchase',
        ]);

        $order = $this->createOrderForBranch($accountant->id);

        $inRangeOrderExpense = OrderExpense::create([
            'order_id' => $order->id,
            'tailor_id' => $tailor->id,
            'amount' => 4500,
            'notes' => 'Special embroidery work',
        ]);
        $this->setOrderExpenseTimestamp($inRangeOrderExpense->id, now());

        $outOfRangeOrderExpense = OrderExpense::create([
            'order_id' => $order->id,
            'tailor_id' => $tailor->id,
            'amount' => 5000,
            'notes' => 'Old tailoring expense',
        ]);
        $this->setOrderExpenseTimestamp($outOfRangeOrderExpense->id, now()->subDays(15));

        $orderOnlyReport = new ExpensesReport([
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'category_id' => 'order_expenses',
        ]);
        $orderOnlySummary = $orderOnlyReport->summary();
        $this->assertSame(1, $orderOnlySummary['total_count']);
        $this->assertEquals(4500.0, $orderOnlySummary['total_expenses']);

        $searchReport = new ExpensesReport([
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'search' => 'embroidery',
        ]);
        $searchRows = $searchReport->rows(20)->getCollection();
        $this->assertCount(1, $searchRows);
        $this->assertSame('order_expense', $searchRows->first()->source_type);

        $regularCategoryReport = new ExpensesReport([
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'category_id' => (string) $category->id,
        ]);
        $regularSummary = $regularCategoryReport->summary();
        $this->assertSame(1, $regularSummary['total_count']);
        $this->assertEquals(8000.0, $regularSummary['total_expenses']);
    }

    protected function createOrderForBranch(int $createdBy): Order
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        return Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'order_date' => now()->toDateString(),
            'subtotal' => 10000,
            'discount' => 0,
            'total' => 10000,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $createdBy,
        ]);
    }

    protected function setOrderExpenseTimestamp(int $orderExpenseId, \DateTimeInterface $timestamp): void
    {
        DB::table('order_expenses')
            ->where('id', $orderExpenseId)
            ->update([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
    }
}

<?php

namespace App\Services\Dashboard;

use App\Enums\CapitalAllocationStatus;
use App\Enums\OrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\CapitalAllocation;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderExpense;
use App\Models\OrderPayment;
use App\Models\PosSale;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;

class DashboardStats
{
    /**
     * Get dashboard statistics for the given user.
     * Returns branch-scoped data based on BranchContext.
     */
    public function for(User $user): array
    {
        $branchId = BranchContext::id();

        // If no branch context for global admin, return empty stats with flag
        if ($user->isGlobalAdmin() && ! $branchId) {
            return $this->emptyStats(needsBranchSelection: true);
        }

        return [
            'needs_branch_selection' => false,
            'orders' => $this->getOrderStats(),
            'sales' => $this->getSalesStats(),
            'inventory' => $this->getInventoryStats(),
            'procurement' => $this->getProcurementStats(),
            'expenses' => $this->getExpenseStats(),
            'capital' => $this->getCapitalStats(),
            'customers' => $this->getCustomerStats(),
        ];
    }

    /**
     * Get order statistics.
     */
    protected function getOrderStats(): array
    {
        [$startOfMonth, $endOfMonth] = $this->monthRange(0);
        [$startOfLastMonth, $endOfLastMonth] = $this->monthRange(-1);

        $newOrdersThisMonth = Order::query()
            ->where('status', OrderStatus::New)
            ->dateRange($startOfMonth->toDateString(), $endOfMonth->toDateString())
            ->count();

        $newOrdersLastMonth = Order::query()
            ->where('status', OrderStatus::New)
            ->dateRange($startOfLastMonth->toDateString(), $endOfLastMonth->toDateString())
            ->count();

        return [
            'new_orders_count' => Order::where('status', OrderStatus::New)->count(),
            'in_progress_orders_count' => Order::whereIn('status', [
                OrderStatus::InProgress,
                OrderStatus::Ready,
            ])->count(),
            'completed_orders_count' => Order::whereIn('status', [
                OrderStatus::Delivered,
                OrderStatus::Completed,
            ])->count(),
            'total_orders_count' => Order::count(),
            'new_orders_month_count' => $newOrdersThisMonth,
            'new_orders_previous_month_count' => $newOrdersLastMonth,
            'new_orders_month_change_pct' => $this->percentageChange(
                (float) $newOrdersThisMonth,
                (float) $newOrdersLastMonth
            ),
        ];
    }

    /**
     * Get sales/payment statistics.
     */
    protected function getSalesStats(): array
    {
        $today = Carbon::today();
        [$startOfMonth, $endOfMonth] = $this->monthRange(0);
        [$startOfLastMonth, $endOfLastMonth] = $this->monthRange(-1);

        $paymentsThisMonth = (float) OrderPayment::query()
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $posSalesThisMonth = (float) PosSale::query()
            ->whereBetween('sold_at', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $paymentsLastMonth = (float) OrderPayment::query()
            ->whereBetween('paid_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');

        $posSalesLastMonth = (float) PosSale::query()
            ->whereBetween('sold_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total_amount');

        $revenueThisMonth = $paymentsThisMonth + $posSalesThisMonth;
        $revenueLastMonth = $paymentsLastMonth + $posSalesLastMonth;

        $storefrontPaymentsThisMonth = (float) OrderPayment::query()
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->whereHas('order', fn ($query) => $query->where('order_type', 'storefront'))
            ->sum('amount');

        $storefrontPaymentsLastMonth = (float) OrderPayment::query()
            ->whereBetween('paid_at', [$startOfLastMonth, $endOfLastMonth])
            ->whereHas('order', fn ($query) => $query->where('order_type', 'storefront'))
            ->sum('amount');

        return [
            'payments_today_sum' => (float) OrderPayment::whereDate('paid_at', $today)->sum('amount')
                + (float) PosSale::whereDate('sold_at', $today)->sum('total_amount'),
            'payments_month_sum' => $revenueThisMonth,
            'payments_last_month_sum' => $revenueLastMonth,
            'payments_month_change_pct' => $this->percentageChange($revenueThisMonth, $revenueLastMonth),
            'pos_sales_month_sum' => $posSalesThisMonth,
            'pos_sales_last_month_sum' => $posSalesLastMonth,
            'storefront_payments_month_sum' => $storefrontPaymentsThisMonth,
            'storefront_payments_last_month_sum' => $storefrontPaymentsLastMonth,
            'storefront_payments_month_change_pct' => $this->percentageChange(
                $storefrontPaymentsThisMonth,
                $storefrontPaymentsLastMonth
            ),
        ];
    }

    /**
     * Get inventory statistics.
     */
    protected function getInventoryStats(): array
    {
        // Low stock count - items where qty_on_hand <= reorder_level
        $lowStockCount = InventoryStock::query()
            ->join('inventory_items', 'inventory_stocks.inventory_item_id', '=', 'inventory_items.id')
            ->whereColumn('inventory_stocks.qty_on_hand', '<=', 'inventory_items.reorder_level')
            ->count();

        return [
            'low_stock_count' => $lowStockCount,
            'total_items_count' => InventoryStock::count(),
        ];
    }

    /**
     * Get procurement statistics.
     */
    protected function getProcurementStats(): array
    {
        return [
            'pending_purchase_requests_count' => PurchaseRequest::where('status', PurchaseRequestStatus::Submitted)->count(),
        ];
    }

    /**
     * Get expense statistics.
     */
    protected function getExpenseStats(): array
    {
        [$startOfMonth, $endOfMonth] = $this->monthRange(0);
        [$startOfLastMonth, $endOfLastMonth] = $this->monthRange(-1);

        $expenseSum = (float) Expense::query()
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $orderExpenseSum = (float) OrderExpense::query()
            ->whereHas('order')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $lastMonthExpenseSum = (float) Expense::query()
            ->whereBetween('expense_date', [$startOfLastMonth->toDateString(), $endOfLastMonth->toDateString()])
            ->sum('amount');

        $lastMonthOrderExpenseSum = (float) OrderExpense::query()
            ->whereHas('order')
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');

        $expensesThisMonth = $expenseSum + $orderExpenseSum;
        $expensesLastMonth = $lastMonthExpenseSum + $lastMonthOrderExpenseSum;

        return [
            'expenses_month_sum' => $expensesThisMonth,
            'expenses_last_month_sum' => $expensesLastMonth,
            'expenses_month_change_pct' => $this->percentageChange($expensesThisMonth, $expensesLastMonth),
        ];
    }

    /**
     * Get capital allocation statistics.
     */
    protected function getCapitalStats(): array
    {
        $openAllocations = CapitalAllocation::where('status', CapitalAllocationStatus::Open)->get();

        $totalRemaining = $openAllocations->sum(function ($allocation) {
            return $allocation->initial_amount - $allocation->spent_amount;
        });

        return [
            'open_allocations_count' => $openAllocations->count(),
            'total_remaining_capital_sum' => (float) $totalRemaining,
        ];
    }

    /**
     * Get customer statistics.
     */
    protected function getCustomerStats(): array
    {
        return [
            'top_by_orders' => Customer::query()
                ->withCount('orders')
                ->has('orders')
                ->orderByDesc('orders_count')
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'phone']),
        ];
    }

    /**
     * Return empty stats structure.
     */
    protected function emptyStats(bool $needsBranchSelection = false): array
    {
        return [
            'needs_branch_selection' => $needsBranchSelection,
            'orders' => [
                'new_orders_count' => 0,
                'in_progress_orders_count' => 0,
                'completed_orders_count' => 0,
                'total_orders_count' => 0,
                'new_orders_month_count' => 0,
                'new_orders_previous_month_count' => 0,
                'new_orders_month_change_pct' => 0.0,
            ],
            'sales' => [
                'payments_today_sum' => 0,
                'payments_month_sum' => 0,
                'payments_last_month_sum' => 0,
                'payments_month_change_pct' => 0.0,
                'pos_sales_month_sum' => 0,
                'pos_sales_last_month_sum' => 0,
                'storefront_payments_month_sum' => 0,
                'storefront_payments_last_month_sum' => 0,
                'storefront_payments_month_change_pct' => 0.0,
            ],
            'inventory' => [
                'low_stock_count' => 0,
                'total_items_count' => 0,
            ],
            'procurement' => [
                'pending_purchase_requests_count' => 0,
            ],
            'expenses' => [
                'expenses_month_sum' => 0,
                'expenses_last_month_sum' => 0,
                'expenses_month_change_pct' => 0.0,
            ],
            'capital' => [
                'open_allocations_count' => 0,
                'total_remaining_capital_sum' => 0,
            ],
            'customers' => [
                'top_by_orders' => collect(),
            ],
        ];
    }

    /**
     * Return calendar month range using an offset where 0 is current month, -1 is last month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function monthRange(int $offset): array
    {
        $reference = Carbon::now()->startOfMonth()->addMonths($offset);

        return [
            $reference->copy()->startOfMonth(),
            $reference->copy()->endOfMonth(),
        ];
    }

    /**
     * Calculate month-over-month percentage change.
     */
    protected function percentageChange(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}

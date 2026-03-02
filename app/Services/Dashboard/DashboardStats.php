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
        ];
    }

    /**
     * Get sales/payment statistics.
     */
    protected function getSalesStats(): array
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        return [
            'payments_today_sum' => (float) OrderPayment::whereDate('paid_at', $today)->sum('amount'),
            'payments_month_sum' => (float) OrderPayment::whereBetween('paid_at', [$startOfMonth, $endOfMonth])->sum('amount'),
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
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $expenseSum = (float) Expense::query()
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $orderExpenseSum = (float) OrderExpense::query()
            ->whereHas('order')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        return [
            'expenses_month_sum' => $expenseSum + $orderExpenseSum,
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
            ],
            'sales' => [
                'payments_today_sum' => 0,
                'payments_month_sum' => 0,
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
}

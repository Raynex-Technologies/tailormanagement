<?php

namespace App\Reports;

use App\Models\Expense;
use App\Models\OrderExpense;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpensesReport
{
    protected ?string $dateFrom;

    protected ?string $dateTo;

    protected ?int $categoryId = null;

    protected ?int $subcategoryId = null;

    protected bool $orderExpensesOnly = false;

    protected ?bool $linkedToCapital;

    protected ?string $search;

    public function __construct(array $filters = [])
    {
        $this->dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = $filters['date_to'] ?? Carbon::now()->endOfMonth()->toDateString();
        $this->setCategoryFilter($filters['category_id'] ?? null);
        $this->setSubcategoryFilter($filters['subcategory_id'] ?? null);
        $this->linkedToCapital = isset($filters['linked_to_capital'])
            ? filter_var($filters['linked_to_capital'], FILTER_VALIDATE_BOOLEAN)
            : null;
        $this->search = isset($filters['search']) ? trim((string) $filters['search']) : null;

        if ($this->search === '') {
            $this->search = null;
        }
    }

    /**
     * Get summary statistics for the report.
     */
    public function summary(): array
    {
        $baseQuery = $this->baseQuery();

        $stats = (clone $baseQuery)->selectRaw('
            COUNT(*) as total_count,
            COALESCE(SUM(amount), 0) as total_expenses
        ')->first();

        $linkedTotal = (clone $baseQuery)
            ->whereNotNull('capital_allocation_id')
            ->sum('amount');

        // Get top category
        $topCategory = (clone $baseQuery)
            ->select('category_name', DB::raw('SUM(amount) as total'))
            ->groupBy('category_name')
            ->orderByDesc('total')
            ->first();

        return [
            'total_expenses' => (float) ($stats->total_expenses ?? 0),
            'total_count' => (int) ($stats->total_count ?? 0),
            'linked_to_capital_total' => (float) $linkedTotal,
            'top_category' => $topCategory?->category_name ?? 'Uncategorized',
            'top_category_amount' => (float) ($topCategory?->total ?? 0),
        ];
    }

    /**
     * Get category breakdown.
     */
    public function categoryBreakdown(): Collection
    {
        return $this->baseQuery()
            ->select(
                DB::raw('COALESCE(category_name, "Uncategorized") as category_name'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('category_name')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Get paginated rows for the report table.
     */
    public function rows(int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->orderByDesc('sort_at')
            ->orderByDesc('source_type')
            ->orderByDesc('source_id')
            ->paginate($perPage);
    }

    /**
     * Export data for CSV.
     */
    public function export(): array
    {
        $rows = $this->baseQuery()
            ->orderByDesc('sort_at')
            ->orderByDesc('source_type')
            ->orderByDesc('source_id')
            ->get();

        $data = [];
        $data[] = ['Date', 'Category', 'Subcategory', 'Vendor', 'Amount', 'Capital Allocation', 'Created By', 'Reference'];

        foreach ($rows as $row) {
            $data[] = [
                Carbon::parse($row->expense_date)->format('Y-m-d'),
                $row->category_name ?? 'Uncategorized',
                $row->subcategory_name ?? '',
                $row->vendor ?? '',
                number_format($row->amount, 2),
                $row->allocation_no ?? '',
                $row->created_by_name ?? '',
                $row->reference ?? '',
            ];
        }

        return $data;
    }

    /**
     * Build the base query with filters.
     */
    protected function baseQuery()
    {
        $query = DB::query()->fromSub(
            $this->regularExpensesQuery()->unionAll($this->orderExpensesQuery()),
            'report_expenses'
        );

        // Date range filter
        if ($this->dateFrom) {
            $query->whereDate('expense_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('expense_date', '<=', $this->dateTo);
        }

        // Category filter
        if ($this->orderExpensesOnly) {
            $query->where('source_type', 'order_expense');
        } elseif ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        if (! $this->orderExpensesOnly && $this->subcategoryId) {
            $query->where('subcategory_id', $this->subcategoryId);
        }

        // Linked to capital filter
        if ($this->linkedToCapital === true) {
            $query->whereNotNull('capital_allocation_id');
        } elseif ($this->linkedToCapital === false) {
            $query->whereNull('capital_allocation_id');
        }

        // Search filter
        if ($this->search) {
            $search = '%' . $this->search . '%';

            $query->where(function ($q) use ($search) {
                $q->where('vendor', 'like', $search)
                    ->orWhere('reference', 'like', $search)
                    ->orWhere('note', 'like', $search)
                    ->orWhere('created_by_name', 'like', $search)
                    ->orWhere('category_name', 'like', $search)
                    ->orWhere('subcategory_name', 'like', $search);
            });
        }

        return $query;
    }

    /**
     * Resolve category filter into regular category or order-expense shortcut.
     */
    protected function setCategoryFilter($categoryFilter): void
    {
        $this->categoryId = null;
        $this->orderExpensesOnly = false;

        if (is_string($categoryFilter) && $categoryFilter === 'order_expenses') {
            $this->orderExpensesOnly = true;

            return;
        }

        if (is_numeric($categoryFilter) && (int) $categoryFilter > 0) {
            $this->categoryId = (int) $categoryFilter;
        }
    }

    /**
     * Resolve subcategory filter into integer id.
     */
    protected function setSubcategoryFilter($subcategoryFilter): void
    {
        $this->subcategoryId = null;

        if (is_numeric($subcategoryFilter) && (int) $subcategoryFilter > 0) {
            $this->subcategoryId = (int) $subcategoryFilter;
        }
    }

    /**
     * Base projection for regular expenses.
     */
    protected function regularExpensesQuery()
    {
        return Expense::query()
            ->select([
                'expenses.id as source_id',
                DB::raw("'expense' as source_type"),
                'expenses.expense_date as expense_date',
                'expenses.expense_date as sort_at',
                'expenses.amount as amount',
                'expenses.expense_category_id as category_id',
                'expenses.expense_subcategory_id as subcategory_id',
                DB::raw('COALESCE(expense_categories.name, "Uncategorized") as category_name'),
                'expense_subcategories.name as subcategory_name',
                'expenses.vendor as vendor',
                'expenses.capital_allocation_id as capital_allocation_id',
                'capital_allocations.allocation_no as allocation_no',
                'users.name as created_by_name',
                'expenses.reference as reference',
                'expenses.note as note',
            ])
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->leftJoin('expense_subcategories', 'expenses.expense_subcategory_id', '=', 'expense_subcategories.id')
            ->leftJoin('capital_allocations', 'expenses.capital_allocation_id', '=', 'capital_allocations.id')
            ->leftJoin('users', 'expenses.created_by', '=', 'users.id');
    }

    /**
     * Base projection for order expenses.
     */
    protected function orderExpensesQuery()
    {
        return OrderExpense::query()
            ->select([
                'order_expenses.id as source_id',
                DB::raw("'order_expense' as source_type"),
                DB::raw('DATE(order_expenses.created_at) as expense_date'),
                'order_expenses.created_at as sort_at',
                'order_expenses.amount as amount',
                DB::raw('NULL as category_id'),
                DB::raw('NULL as subcategory_id'),
                DB::raw("'Order Expenses' as category_name"),
                DB::raw('NULL as subcategory_name'),
                'tailors.name as vendor',
                DB::raw('NULL as capital_allocation_id'),
                DB::raw('NULL as allocation_no'),
                'tailors.name as created_by_name',
                'orders.order_no as reference',
                'order_expenses.notes as note',
            ])
            ->join('orders', 'order_expenses.order_id', '=', 'orders.id')
            ->leftJoin('users as tailors', 'order_expenses.tailor_id', '=', 'tailors.id')
            ->whereHas('order');
    }
}

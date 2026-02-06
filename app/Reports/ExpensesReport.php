<?php

namespace App\Reports;

use App\Models\Expense;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpensesReport
{
    protected ?string $dateFrom;

    protected ?string $dateTo;

    protected ?int $categoryId;

    protected ?bool $linkedToCapital;

    protected ?string $search;

    public function __construct(array $filters = [])
    {
        $this->dateFrom = $filters['date_from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = $filters['date_to'] ?? Carbon::now()->endOfMonth()->toDateString();
        $this->categoryId = $filters['category_id'] ?? null;
        $this->linkedToCapital = isset($filters['linked_to_capital'])
            ? filter_var($filters['linked_to_capital'], FILTER_VALIDATE_BOOLEAN)
            : null;
        $this->search = $filters['search'] ?? null;
    }

    /**
     * Get summary statistics for the report.
     */
    public function summary(): array
    {
        $baseQuery = $this->baseQuery();

        $stats = (clone $baseQuery)->selectRaw('
            COUNT(*) as total_count,
            COALESCE(SUM(expenses.amount), 0) as total_expenses
        ')->first();

        $linkedTotal = (clone $baseQuery)
            ->whereNotNull('expenses.capital_allocation_id')
            ->sum('expenses.amount');

        // Get top category
        $topCategory = (clone $baseQuery)
            ->select('expense_categories.name', DB::raw('SUM(expenses.amount) as total'))
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->first();

        return [
            'total_expenses' => (float) ($stats->total_expenses ?? 0),
            'total_count' => (int) ($stats->total_count ?? 0),
            'linked_to_capital_total' => (float) $linkedTotal,
            'top_category' => $topCategory?->name ?? 'Uncategorized',
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
                DB::raw('COALESCE(expense_categories.name, "Uncategorized") as category_name'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(expenses.amount) as total')
            )
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Get paginated rows for the report table.
     */
    public function rows(int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->select([
                'expenses.*',
                'expense_categories.name as category_name',
                'capital_allocations.allocation_no',
                'users.name as created_by_name',
            ])
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->leftJoin('capital_allocations', 'expenses.capital_allocation_id', '=', 'capital_allocations.id')
            ->leftJoin('users', 'expenses.created_by', '=', 'users.id')
            ->orderByDesc('expenses.expense_date')
            ->paginate($perPage);
    }

    /**
     * Export data for CSV.
     */
    public function export(): array
    {
        $rows = $this->baseQuery()
            ->select([
                'expenses.*',
                'expense_categories.name as category_name',
                'capital_allocations.allocation_no',
                'users.name as created_by_name',
            ])
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->leftJoin('capital_allocations', 'expenses.capital_allocation_id', '=', 'capital_allocations.id')
            ->leftJoin('users', 'expenses.created_by', '=', 'users.id')
            ->orderByDesc('expenses.expense_date')
            ->get();

        $data = [];
        $data[] = ['Date', 'Category', 'Vendor', 'Amount', 'Capital Allocation', 'Created By', 'Reference'];

        foreach ($rows as $row) {
            $data[] = [
                Carbon::parse($row->expense_date)->format('Y-m-d'),
                $row->category_name ?? 'Uncategorized',
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
        $query = Expense::query();

        // Date range filter
        if ($this->dateFrom) {
            $query->whereDate('expenses.expense_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('expenses.expense_date', '<=', $this->dateTo);
        }

        // Category filter
        if ($this->categoryId) {
            $query->where('expenses.expense_category_id', $this->categoryId);
        }

        // Linked to capital filter
        if ($this->linkedToCapital === true) {
            $query->whereNotNull('expenses.capital_allocation_id');
        } elseif ($this->linkedToCapital === false) {
            $query->whereNull('expenses.capital_allocation_id');
        }

        // Search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('expenses.vendor', 'like', "%{$this->search}%")
                    ->orWhere('expenses.reference', 'like', "%{$this->search}%")
                    ->orWhere('expenses.note', 'like', "%{$this->search}%");
            });
        }

        return $query;
    }
}

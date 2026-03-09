<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\OrderExpense;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public string $categoryFilter = '';
    public string $subcategoryFilter = '';
    public string $linkedToCapitalFilter = '';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public int $perPage = 15;

    protected string $paginationTheme = 'tailwind';

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'subcategoryFilter' => ['except' => ''],
        'linkedToCapitalFilter' => ['except' => ''],
        'dateFrom' => ['except' => null],
        'dateTo' => ['except' => null],
    ];

    public function mount(): void
    {
        $this->authorize('expenses.view');

        if (empty($this->dateFrom)) {
            $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        }

        if (empty($this->dateTo)) {
            $this->dateTo = now()->endOfMonth()->format('Y-m-d');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->subcategoryFilter = '';
        $this->resetPage();
    }

    public function updatingSubcategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingLinkedToCapitalFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->categoryFilter = '';
        $this->subcategoryFilter = '';
        $this->linkedToCapitalFilter = '';
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }

    public function render()
    {
        $filteredQuery = $this->baseExpensesQuery();
        $this->applyFiltersToQuery($filteredQuery);

        $expenses = (clone $filteredQuery)
            ->orderByDesc('sort_at')
            ->orderByDesc('source_type')
            ->orderByDesc('source_id')
            ->paginate($this->perPage);

        $stats = [
            'total_amount' => (float) (clone $filteredQuery)->sum('amount'),
            'count' => (int) (clone $filteredQuery)->count(),
            'linked_to_capital' => (int) (clone $filteredQuery)->whereNotNull('capital_allocation_id')->count(),
        ];

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $currentMonthQuery = $this->baseExpensesQuery()
            ->whereDate('expense_date', '>=', $monthStart)
            ->whereDate('expense_date', '<=', $monthEnd);

        $currentMonthStats = [
            'total' => (float) (clone $currentMonthQuery)->sum('amount'),
            'count' => (int) (clone $currentMonthQuery)->count(),
        ];

        $categories = ExpenseCategory::orderBy('name')
            ->get(['id', 'name'])
            ->prepend((object) [
                'id' => 'order_expenses',
                'name' => 'Order Expenses',
            ]);

        if ($this->categoryFilter === 'order_expenses') {
            $this->subcategoryFilter = '';
            $subcategories = collect();
        } else {
            $subcategoriesQuery = ExpenseSubcategory::query()->orderBy('name');
            if ($this->categoryFilter !== '') {
                $subcategoriesQuery->where('expense_category_id', (int) $this->categoryFilter);
            }
            $subcategories = $subcategoriesQuery->get(['id', 'name']);
        }

        if (
            $this->subcategoryFilter !== ''
            && ! $subcategories->contains(fn ($subcategory) => (string) $subcategory->id === $this->subcategoryFilter)
        ) {
            $this->subcategoryFilter = '';
        }

        return view('livewire.expenses.index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'stats' => $stats,
            'currentMonthStats' => $currentMonthStats,
        ])->title(__('Expenses'));
    }

    protected function baseExpensesQuery(): Builder
    {
        return DB::query()->fromSub(
            $this->regularExpensesQuery()->unionAll($this->orderExpensesQuery()),
            'expenses_list'
        );
    }

    protected function applyFiltersToQuery(Builder $query): void
    {
        if ($this->search !== '') {
            $search = '%' . trim($this->search) . '%';

            $query->where(function ($q) use ($search) {
                $q->where('vendor', 'like', $search)
                    ->orWhere('reference', 'like', $search)
                    ->orWhere('note', 'like', $search)
                    ->orWhere('created_by_name', 'like', $search)
                    ->orWhere('category_name', 'like', $search)
                    ->orWhere('subcategory_name', 'like', $search);
            });
        }

        if ($this->categoryFilter === 'order_expenses') {
            $query->where('source_type', 'order_expense');
        } elseif ($this->categoryFilter !== '') {
            $query->where('category_id', (int) $this->categoryFilter);
        }

        if ($this->subcategoryFilter !== '' && $this->categoryFilter !== 'order_expenses') {
            $query->where('subcategory_id', (int) $this->subcategoryFilter);
        }

        if ($this->linkedToCapitalFilter === 'yes') {
            $query->whereNotNull('capital_allocation_id');
        } elseif ($this->linkedToCapitalFilter === 'no') {
            $query->whereNull('capital_allocation_id');
        }

        if ($this->dateFrom) {
            $query->whereDate('expense_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('expense_date', '<=', $this->dateTo);
        }
    }

    protected function regularExpensesQuery()
    {
        return Expense::query()
            ->select([
                'expenses.id as source_id',
                DB::raw("'expense' as source_type"),
                'expenses.id as expense_id',
                DB::raw('NULL as order_id'),
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

    protected function orderExpensesQuery()
    {
        return OrderExpense::query()
            ->select([
                'order_expenses.id as source_id',
                DB::raw("'order_expense' as source_type"),
                DB::raw('NULL as expense_id'),
                'orders.id as order_id',
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

<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public ?int $categoryFilter = null;
    public ?string $linkedToCapitalFilter = null; // 'yes', 'no', null
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public int $perPage = 15;

    protected string $paginationTheme = 'tailwind';

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => null],
        'linkedToCapitalFilter' => ['except' => null],
        'dateFrom' => ['except' => null],
        'dateTo' => ['except' => null],
    ];

    public function mount(): void
    {
        $this->authorize('expenses.view');

        // Default to current month
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
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
        $this->reset(['search', 'categoryFilter', 'linkedToCapitalFilter']);
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }

    public function render()
    {
        $query = Expense::with(['category', 'capitalAllocation', 'creator'])
            ->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Apply search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('vendor', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")
                    ->orWhere('note', 'like', "%{$this->search}%");
            });
        }

        // Apply category filter
        if ($this->categoryFilter) {
            $query->where('expense_category_id', $this->categoryFilter);
        }

        // Apply capital linkage filter
        if ($this->linkedToCapitalFilter === 'yes') {
            $query->whereNotNull('capital_allocation_id');
        } elseif ($this->linkedToCapitalFilter === 'no') {
            $query->whereNull('capital_allocation_id');
        }

        // Apply date range
        if ($this->dateFrom) {
            $query->whereDate('expense_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('expense_date', '<=', $this->dateTo);
        }

        $expenses = $query->paginate($this->perPage);

        // Calculate summary stats using the same filters
        $statsQuery = Expense::query();
        if ($this->dateFrom) {
            $statsQuery->whereDate('expense_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $statsQuery->whereDate('expense_date', '<=', $this->dateTo);
        }

        $stats = [
            'total_amount' => (clone $statsQuery)->sum('amount'),
            'count' => (clone $statsQuery)->count(),
            'linked_to_capital' => (clone $statsQuery)->whereNotNull('capital_allocation_id')->count(),
        ];

        // Current month stats (regardless of filters)
        $currentMonthStats = [
            'total' => Expense::whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount'),
            'count' => Expense::whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->count(),
        ];

        $categories = ExpenseCategory::orderBy('name')->get(['id', 'name']);

        return view('livewire.expenses.index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'stats' => $stats,
            'currentMonthStats' => $currentMonthStats,
        ])->title(__('Expenses'));
    }
}

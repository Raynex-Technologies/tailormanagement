<?php

namespace App\Livewire\Reports;

use App\Models\ExpenseCategory;
use App\Reports\ExpensesReport;
use App\Support\BranchContext;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app.sidebar')]
#[Title('Expenses Report')]
class Expenses extends Component
{
    use WithPagination;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $categoryId = '';

    #[Url]
    public string $linkedToCapital = '';

    #[Url]
    public string $search = '';

    protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        if (! auth()->user()->can('reports.view')) {
            abort(403);
        }

        if (empty($this->dateFrom)) {
            $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
        }
        if (empty($this->dateTo)) {
            $this->dateTo = Carbon::now()->endOfMonth()->toDateString();
        }
    }

    #[Computed]
    public function report(): ExpensesReport
    {
        return new ExpensesReport([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'category_id' => $this->categoryId ? (int) $this->categoryId : null,
            'linked_to_capital' => $this->linkedToCapital !== '' ? $this->linkedToCapital : null,
            'search' => $this->search ?: null,
        ]);
    }

    #[Computed]
    public function summary(): array
    {
        return $this->report->summary();
    }

    #[Computed]
    public function rows()
    {
        return $this->report->rows();
    }

    #[Computed]
    public function categoryBreakdown()
    {
        return $this->report->categoryBreakdown();
    }

    #[Computed]
    public function categories()
    {
        return ExpenseCategory::orderBy('name')->get(['id', 'name']);
    }

    public function resetFilters(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = Carbon::now()->endOfMonth()->toDateString();
        $this->categoryId = '';
        $this->linkedToCapital = '';
        $this->search = '';
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        if (! auth()->user()->can('reports.export')) {
            abort(403);
        }

        $data = $this->report->export();

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'expenses-report-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.reports.expenses');
    }
}

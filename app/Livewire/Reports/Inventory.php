<?php

namespace App\Livewire\Reports;

use App\Models\InventoryCategory;
use App\Reports\InventoryReport;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app.sidebar')]
#[Title('Inventory Report')]
class Inventory extends Component
{
    use WithPagination;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $categoryId = '';

    #[Url]
    public bool $lowStockOnly = false;

    #[Url]
    public string $search = '';

    #[Url]
    public string $view = 'stock'; // stock or movement

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
    public function report(): InventoryReport
    {
        return new InventoryReport([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'category_id' => $this->categoryId ? (int) $this->categoryId : null,
            'low_stock_only' => $this->lowStockOnly,
            'search' => $this->search ?: null,
        ]);
    }

    #[Computed]
    public function summary(): array
    {
        return $this->report->summary();
    }

    #[Computed]
    public function stockLevels()
    {
        return $this->report->stockLevels();
    }

    #[Computed]
    public function movementSummary()
    {
        return $this->report->movementSummary();
    }

    #[Computed]
    public function categories()
    {
        return InventoryCategory::orderBy('name')->get(['id', 'name']);
    }

    public function setView(string $view): void
    {
        $this->view = $view;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = Carbon::now()->endOfMonth()->toDateString();
        $this->categoryId = '';
        $this->lowStockOnly = false;
        $this->search = '';
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        if (! auth()->user()->can('reports.export')) {
            abort(403);
        }

        $data = $this->report->exportStockLevels();

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'inventory-report-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.reports.inventory');
    }
}

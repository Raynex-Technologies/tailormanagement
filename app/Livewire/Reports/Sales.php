<?php

namespace App\Livewire\Reports;

use App\Reports\SalesReport;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app.sidebar')]
#[Title('Sales Report')]
class Sales extends Component
{
    use WithPagination;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $method = '';

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
    public function report(): SalesReport
    {
        return new SalesReport([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'method' => $this->method ?: null,
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
    public function paymentMethods(): array
    {
        return ['cash', 'mpesa', 'bank_transfer', 'card', 'other'];
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = Carbon::now()->endOfMonth()->toDateString();
        $this->method = '';
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
        }, 'sales-report-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.reports.sales');
    }
}

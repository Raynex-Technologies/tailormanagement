<?php

namespace App\Livewire\Installments;

use App\Enums\InstallmentFrequency;
use App\Enums\InstallmentPlanStatus;
use App\Reports\InstallmentsReport;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app.sidebar')]
#[Title('Installments Analytics')]
class Analytics extends Component
{
    use WithPagination;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $frequency = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        if (! auth()->user()->can('installments.analytics.view')) {
            abort(403);
        }

        $this->dateFrom = $this->dateFrom ?: Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = $this->dateTo ?: Carbon::now()->endOfMonth()->toDateString();
    }

    #[Computed]
    public function report(): InstallmentsReport
    {
        return new InstallmentsReport([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'status' => $this->status ?: null,
            'frequency' => $this->frequency ?: null,
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

    public function resetFilters(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = Carbon::now()->endOfMonth()->toDateString();
        $this->status = '';
        $this->frequency = '';
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
        }, 'installments-report-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.installments.analytics', [
            'statuses' => InstallmentPlanStatus::cases(),
            'frequencies' => InstallmentFrequency::cases(),
        ]);
    }
}

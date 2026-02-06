<?php

namespace App\Livewire\Reports;

use App\Enums\CapitalAllocationStatus;
use App\Models\User;
use App\Reports\CapitalAuditReport;
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
#[Title('Capital Audit Report')]
class Capital extends Component
{
    use WithPagination;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $accountantId = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $view = 'allocations'; // allocations or transactions

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
    public function report(): CapitalAuditReport
    {
        return new CapitalAuditReport([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'accountant_id' => $this->accountantId ? (int) $this->accountantId : null,
            'status' => $this->status ?: null,
        ]);
    }

    #[Computed]
    public function summary(): array
    {
        return $this->report->summary();
    }

    #[Computed]
    public function allocations()
    {
        return $this->report->allocations();
    }

    #[Computed]
    public function transactions()
    {
        return $this->report->transactions();
    }

    #[Computed]
    public function accountants()
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'accountant'))
            ->where('branch_id', BranchContext::id())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function statuses(): array
    {
        return CapitalAllocationStatus::cases();
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
        $this->accountantId = '';
        $this->status = '';
        $this->resetPage();
    }

    public function exportAllocations(): StreamedResponse
    {
        if (! auth()->user()->can('reports.export')) {
            abort(403);
        }

        $data = $this->report->exportAllocations();

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'capital-allocations-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportTransactions(): StreamedResponse
    {
        if (! auth()->user()->can('reports.export')) {
            abort(403);
        }

        $data = $this->report->exportTransactions();

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'capital-transactions-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.reports.capital');
    }
}

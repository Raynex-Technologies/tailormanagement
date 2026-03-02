<?php

namespace App\Livewire\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\User;
use App\Reports\OrdersReport;
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
#[Title('Orders Report')]
class Orders extends Component
{
    use WithPagination;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $tailorId = '';

    #[Url]
    public string $paymentStatus = '';

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
    public function report(): OrdersReport
    {
        return new OrdersReport([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'status' => $this->status ?: null,
            'tailor_id' => $this->tailorId ? (int) $this->tailorId : null,
            'payment_status' => $this->paymentStatus ?: null,
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
    public function statuses(): array
    {
        return OrderStatus::cases();
    }

    #[Computed]
    public function paymentStatuses(): array
    {
        return PaymentStatus::cases();
    }

    #[Computed]
    public function tailors()
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'tailor'))
            ->where('branch_id', BranchContext::id())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedTailorId(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = Carbon::now()->endOfMonth()->toDateString();
        $this->status = '';
        $this->tailorId = '';
        $this->paymentStatus = '';
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
        }, 'orders-report-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.reports.orders');
    }
}

<?php

namespace App\Livewire\Store\StockRequests;

use App\Enums\StockRequestStatus;
use App\Models\OrderStockRequest;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Stock Requests Inbox')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public int $perPage = 15;

    public function mount(): void
    {
        if (! auth()->user()->can('stock_requests.view')) {
            abort(403);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $query = OrderStockRequest::query()
            ->with(['order.customer', 'requester', 'handler', 'items'])
            ->search($this->search);

        // Apply status filter
        if ($this->statusFilter) {
            $query->status($this->statusFilter);
        }

        $requests = $query->latest()->paginate($this->perPage);

        // Get counts per status
        $baseCounts = OrderStockRequest::query();
        $statusCounts = [
            'requested' => (clone $baseCounts)->status(StockRequestStatus::Requested)->count(),
            'approved' => (clone $baseCounts)->status(StockRequestStatus::Approved)->count(),
            'fulfilled' => (clone $baseCounts)->status(StockRequestStatus::Fulfilled)->count(),
            'declined' => (clone $baseCounts)->status(StockRequestStatus::Declined)->count(),
        ];

        $statuses = collect(StockRequestStatus::cases())
            ->mapWithKeys(fn ($status) => [$status->value => $status->label()]);

        return view('livewire.store.stock-requests.index', [
            'requests' => $requests,
            'statuses' => $statuses,
            'statusCounts' => $statusCounts,
        ]);
    }
}

<?php

namespace App\Livewire\Procurement\Requests;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public ?string $statusFilter = null;

    protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->authorize('procurement.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(?string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function render()
    {
        $query = PurchaseRequest::with(['requester', 'reviewer', 'capitalAllocation'])
            ->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('request_no', 'like', "%{$this->search}%")
                    ->orWhereHas('requester', fn ($rq) => $rq->where('name', 'like', "%{$this->search}%"));
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $requests = $query->paginate(15);

        // Stats per status
        $statusCounts = PurchaseRequest::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('livewire.procurement.requests.index', [
            'requests' => $requests,
            'statuses' => PurchaseRequestStatus::cases(),
            'statusCounts' => $statusCounts,
        ])->title(__('Purchase Requests'));
    }
}

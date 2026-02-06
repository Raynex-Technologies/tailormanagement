<?php

namespace App\Livewire\Procurement\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
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
        $this->authorize('procurement.po.manage');
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
        $query = PurchaseOrder::with(['supplier', 'creator', 'purchaseRequest'])
            ->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('po_no', 'like', "%{$this->search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$this->search}%"));
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $purchaseOrders = $query->paginate(15);

        // Stats
        $statusCounts = PurchaseOrder::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('livewire.procurement.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'statuses' => PurchaseOrderStatus::cases(),
            'statusCounts' => $statusCounts,
        ])->title(__('Purchase Orders'));
    }
}

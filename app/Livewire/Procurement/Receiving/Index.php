<?php

namespace App\Livewire\Procurement\Receiving;

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

    protected string $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->authorize('procurement.receive');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = PurchaseOrder::with(['supplier', 'items'])
            ->whereIn('status', [
                PurchaseOrderStatus::Sent,
                PurchaseOrderStatus::PartiallyReceived,
            ])
            ->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('po_no', 'like', "%{$this->search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$this->search}%"));
            });
        }

        $purchaseOrders = $query->paginate(15);

        return view('livewire.procurement.receiving.index', [
            'purchaseOrders' => $purchaseOrders,
        ])->title(__('Goods Receiving'));
    }
}

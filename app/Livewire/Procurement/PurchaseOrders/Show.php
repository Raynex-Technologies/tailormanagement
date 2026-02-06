<?php

namespace App\Livewire\Procurement\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Notifications\PurchaseOrderCreated;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    use AuthorizesRequests;

    public PurchaseOrder $purchaseOrder;

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->authorize('view', $purchaseOrder);
        $this->purchaseOrder = $purchaseOrder->load([
            'supplier',
            'creator',
            'items.inventoryItem',
            'purchaseRequest.requester',
            'goodsReceipts.items',
        ]);
    }

    public function markAsSent(): void
    {
        $this->authorize('markSent', $this->purchaseOrder);

        $this->purchaseOrder->update(['status' => PurchaseOrderStatus::Sent]);
        $this->purchaseOrder->refresh();

        // Notify stakeholders
        $this->notifyPOSent();

        session()->flash('success', 'Purchase order marked as sent to supplier.');
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->purchaseOrder);

        $this->purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled]);
        $this->purchaseOrder->refresh();

        session()->flash('success', 'Purchase order cancelled.');
    }

    protected function notifyPOSent(): void
    {
        $recipients = collect();

        // Notify storekeepers in branch
        $storekeepers = \App\Models\User::role('storekeeper')
            ->where('branch_id', $this->purchaseOrder->branch_id)
            ->get();
        $recipients = $recipients->merge($storekeepers);

        // Notify accountants in branch
        $accountants = \App\Models\User::role('accountant')
            ->where('branch_id', $this->purchaseOrder->branch_id)
            ->get();
        $recipients = $recipients->merge($accountants);

        // Remove duplicates and self
        $recipients = $recipients->unique('id')
            ->filter(fn ($user) => $user->id !== auth()->id());

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new PurchaseOrderCreated($this->purchaseOrder));
        }
    }

    public function getTotalReceivedProperty(): float
    {
        return $this->purchaseOrder->items->sum('qty_received');
    }

    public function getTotalOrderedProperty(): float
    {
        return $this->purchaseOrder->items->sum('qty_ordered');
    }

    public function render()
    {
        return view('livewire.procurement.purchase-orders.show', [
            'totalReceived' => $this->totalReceived,
            'totalOrdered' => $this->totalOrdered,
        ])->title("PO: {$this->purchaseOrder->po_no}");
    }
}

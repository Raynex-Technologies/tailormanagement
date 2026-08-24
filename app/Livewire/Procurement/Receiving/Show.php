<?php

namespace App\Livewire\Procurement\Receiving;

use App\Models\PurchaseOrder;
use App\Services\Procurement\ReceivingService;
use App\Support\Livewire\NormalizesMoneyInputs;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    use AuthorizesRequests;
    use NormalizesMoneyInputs;

    public PurchaseOrder $purchaseOrder;

    // Receiving form
    public array $receivingItems = [];

    public ?string $note = null;

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->authorize('receive', $purchaseOrder);
        $this->purchaseOrder = $purchaseOrder->load([
            'supplier',
            'items.inventoryItem',
            'goodsReceipts.items',
        ]);

        // Initialize receiving items
        foreach ($this->purchaseOrder->items as $item) {
            $pendingQty = $item->qty_ordered - $item->qty_received;
            $this->receivingItems[$item->id] = [
                'purchase_order_item_id' => $item->id,
                'qty_received' => $pendingQty > 0 ? $pendingQty : 0,
                'unit_cost' => $item->unit_cost,
            ];
        }
    }

    public function receive(ReceivingService $service): void
    {
        $this->normalizeMoneyInputs();
        $this->authorize('receive', $this->purchaseOrder);

        // Filter out items with zero qty
        $itemsToReceive = collect($this->receivingItems)
            ->filter(fn ($item) => ($item['qty_received'] ?? 0) > 0)
            ->values()
            ->toArray();

        if (empty($itemsToReceive)) {
            session()->flash('error', 'Please enter quantities to receive.');

            return;
        }

        try {
            $grn = $service->receive(
                $this->purchaseOrder,
                $itemsToReceive,
                auth()->user(),
                $this->note
            );

            session()->flash('success', "Goods Receipt {$grn->grn_no} created successfully.");

            // Redirect back to PO show page
            $this->redirect(route('procurement.pos.show', $this->purchaseOrder), navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to receive goods: '.$e->getMessage());
        }
    }

    public function getTotalToReceiveProperty(): float
    {
        return collect($this->receivingItems)->sum(fn ($item) => (float) ($item['qty_received'] ?? 0));
    }

    public function render()
    {
        return view('livewire.procurement.receiving.show', [
            'totalToReceive' => $this->totalToReceive,
        ])->title("Receive: {$this->purchaseOrder->po_no}");
    }
}

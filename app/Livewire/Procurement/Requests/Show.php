<?php

namespace App\Livewire\Procurement\Requests;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\Capital\CapitalAllocationService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    use AuthorizesRequests;

    public PurchaseRequest $purchaseRequest;

    // Review form
    public array $reviewedItems = [];
    public ?string $reviewNote = null;

    // Convert to PO form
    public bool $showConvertModal = false;
    public ?int $supplierId = null;
    public ?string $expectedDate = null;
    public ?string $poNote = null;

    public function mount(PurchaseRequest $purchaseRequest): void
    {
        $this->authorize('view', $purchaseRequest);
        $this->purchaseRequest = $purchaseRequest->load([
            'requester',
            'reviewer',
            'items.inventoryItem',
            'capitalAllocation',
            'purchaseOrder.supplier',
        ]);

        // Initialize reviewed items for accountant review
        foreach ($this->purchaseRequest->items as $item) {
            $this->reviewedItems[$item->id] = [
                'id' => $item->id,
                'qty' => $item->qty,
                'unit_price_est' => $item->unit_price_est,
            ];
        }
    }

    public function submit(PurchaseRequestService $service): void
    {
        $this->authorize('submit', $this->purchaseRequest);

        try {
            $service->submit($this->purchaseRequest, auth()->user());
            $this->purchaseRequest->refresh();
            $this->purchaseRequest->load(['requester', 'reviewer', 'items.inventoryItem']);

            session()->flash('success', 'Purchase request submitted for review.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function approve(PurchaseRequestService $service): void
    {
        $this->authorize('approve', $this->purchaseRequest);

        try {
            $items = array_values($this->reviewedItems);
            $service->approve($this->purchaseRequest, auth()->user(), $items, $this->reviewNote);

            $this->purchaseRequest->refresh();
            $this->purchaseRequest->load(['requester', 'reviewer', 'items.inventoryItem', 'capitalAllocation']);

            session()->flash('success', 'Purchase request approved.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function decline(PurchaseRequestService $service): void
    {
        $this->authorize('decline', $this->purchaseRequest);

        try {
            $service->decline($this->purchaseRequest, auth()->user(), $this->reviewNote);

            $this->purchaseRequest->refresh();
            $this->purchaseRequest->load(['requester', 'reviewer', 'items.inventoryItem']);

            session()->flash('success', 'Purchase request declined.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openConvertModal(): void
    {
        $this->authorize('convertToPo', $this->purchaseRequest);
        $this->expectedDate = now()->addDays(7)->format('Y-m-d');
        $this->showConvertModal = true;
    }

    public function convertToPo(PurchaseRequestService $service): void
    {
        $this->authorize('convertToPo', $this->purchaseRequest);

        $this->validate([
            'supplierId' => ['required', 'exists:suppliers,id'],
            'expectedDate' => ['nullable', 'date'],
        ]);

        try {
            $po = $service->convertToPo($this->purchaseRequest, auth()->user(), [
                'supplier_id' => $this->supplierId,
                'expected_date' => $this->expectedDate,
                'note' => $this->poNote,
            ]);

            session()->flash('success', "Purchase Order {$po->po_no} created successfully.");
            $this->redirect(route('procurement.pos.show', $po), navigate: true);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function getReviewedTotalProperty(): float
    {
        return collect($this->reviewedItems)->sum(function ($item) {
            return ((float) ($item['qty'] ?? 0)) * ((float) ($item['unit_price_est'] ?? 0));
        });
    }

    public function getAvailableBalanceProperty(): ?float
    {
        $user = auth()->user();

        // Find active allocation for current user (if accountant)
        if ($user->hasRole('accountant')) {
            $allocation = app(CapitalAllocationService::class)
                ->findActiveAllocationForAccountant($user->id, $this->purchaseRequest->branch_id);

            if ($allocation) {
                return app(CapitalAllocationService::class)->availableBalance($allocation);
            }
        }

        return null;
    }

    public function render()
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        return view('livewire.procurement.requests.show', [
            'suppliers' => $suppliers,
            'reviewedTotal' => $this->reviewedTotal,
            'availableBalance' => $this->availableBalance,
        ])->title("PR: {$this->purchaseRequest->request_no}");
    }
}

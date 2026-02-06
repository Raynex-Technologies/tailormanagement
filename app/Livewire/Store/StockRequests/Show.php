<?php

namespace App\Livewire\Store\StockRequests;

use App\Enums\StockRequestStatus;
use App\Models\OrderStockRequest;
use App\Services\Orders\StockRequestService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    public OrderStockRequest $stockRequest;

    // Review modal
    public bool $showReviewModal = false;
    public string $reviewDecision = 'approve';
    public array $approvedItems = [];
    public string $reviewNote = '';

    // Fulfill modal
    public bool $showFulfillModal = false;
    public array $issueItems = [];
    public string $fulfillNote = '';

    public function mount(OrderStockRequest $stockRequest): void
    {
        $this->authorize('view', $stockRequest);
        $this->stockRequest = $stockRequest->load([
            'order.customer',
            'items.inventoryItem.stock',
            'requester',
            'handler',
        ]);
    }

    public function getTitle(): string
    {
        return "Stock Request #{$this->stockRequest->id}";
    }

    public function openReviewModal(): void
    {
        $this->authorize('review', $this->stockRequest);

        $this->reviewDecision = 'approve';
        $this->reviewNote = '';
        $this->approvedItems = $this->stockRequest->items
            ->map(fn ($item) => [
                'id' => $item->id,
                'qty_approved' => $item->qty_requested,
            ])
            ->toArray();

        $this->showReviewModal = true;
    }

    public function submitReview(StockRequestService $service): void
    {
        $this->authorize('review', $this->stockRequest);

        try {
            $service->reviewRequest(
                $this->stockRequest,
                $this->reviewDecision,
                $this->approvedItems,
                $this->reviewNote ?: null,
                auth()->user()
            );

            $this->stockRequest = $this->stockRequest->fresh([
                'order.customer',
                'items.inventoryItem.stock',
                'requester',
                'handler',
            ]);

            $this->showReviewModal = false;

            $action = $this->reviewDecision === 'approve' ? 'approved' : 'declined';
            session()->flash('success', "Stock request has been {$action}.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        } catch (\Exception $e) {
            $this->addError('review', 'Failed to review request: '.$e->getMessage());
        }
    }

    public function openFulfillModal(): void
    {
        $this->authorize('fulfill', $this->stockRequest);

        $this->fulfillNote = '';
        $this->issueItems = $this->stockRequest->items
            ->map(fn ($item) => [
                'id' => $item->id,
                'qty_to_issue' => $item->remaining_to_issue,
            ])
            ->toArray();

        $this->showFulfillModal = true;
    }

    public function submitFulfill(StockRequestService $service): void
    {
        $this->authorize('fulfill', $this->stockRequest);

        try {
            $service->fulfillRequest(
                $this->stockRequest,
                $this->issueItems,
                $this->fulfillNote ?: null,
                auth()->user()
            );

            $this->stockRequest = $this->stockRequest->fresh([
                'order.customer',
                'items.inventoryItem.stock',
                'requester',
                'handler',
            ]);

            $this->showFulfillModal = false;
            session()->flash('success', 'Stock has been issued successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        } catch (\Exception $e) {
            $this->addError('fulfill', 'Failed to fulfill request: '.$e->getMessage());
        }
    }

    public function render()
    {
        $user = auth()->user();

        // Permission checks
        $canReview = $user->can('review', $this->stockRequest);
        $canFulfill = $user->can('fulfill', $this->stockRequest);

        return view('livewire.store.stock-requests.show', [
            'canReview' => $canReview,
            'canFulfill' => $canFulfill,
        ])->title($this->getTitle());
    }
}

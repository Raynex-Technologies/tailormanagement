<?php

namespace App\Livewire\Orders\StockRequests;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderStockRequest;
use App\Services\Orders\StockRequestService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    public Order $order;

    // New request modal
    public bool $showNewRequestModal = false;
    public array $requestItems = [];
    public string $requestNote = '';
    public string $itemSearch = '';
    public ?int $activeSearchIndex = null;

    // View request detail
    public ?int $viewingRequestId = null;

    public function mount(Order $order): void
    {
        // Check if user can view this order
        $this->authorize('view', $order);

        // Also check if they can view stock requests
        if (! auth()->user()->can('stock_requests.view')) {
            abort(403);
        }

        $this->order = $order->load('customer');
    }

    public function getTitle(): string
    {
        return "Stock Requests - {$this->order->order_no}";
    }

    public function openNewRequestModal(): void
    {
        $this->authorize('create', [OrderStockRequest::class, $this->order]);

        $this->reset(['requestItems', 'requestNote', 'itemSearch', 'activeSearchIndex']);
        $this->addRequestItem();
        $this->showNewRequestModal = true;
    }

    public function addRequestItem(): void
    {
        $this->requestItems[] = [
            'inventory_item_id' => null,
            'inventory_item_name' => '',
            'qty_requested' => 1,
            'note' => '',
        ];
    }

    public function removeRequestItem(int $index): void
    {
        if (count($this->requestItems) > 1) {
            unset($this->requestItems[$index]);
            $this->requestItems = array_values($this->requestItems);
        }
    }

    public function selectItem(int $index, int $itemId): void
    {
        $item = InventoryItem::find($itemId);
        if ($item) {
            $this->requestItems[$index]['inventory_item_id'] = $item->id;
            $this->requestItems[$index]['inventory_item_name'] = $item->name;
            $this->activeSearchIndex = null;
            $this->itemSearch = '';
        }
    }

    /**
     * Set which row's search dropdown is active.
     */
    public function setActiveSearch(int $index): void
    {
        $this->activeSearchIndex = $index;
        $this->itemSearch = '';
    }

    /**
     * Clear a selected item from a row so the user can search again.
     */
    public function clearItem(int $index): void
    {
        $this->requestItems[$index]['inventory_item_id'] = null;
        $this->requestItems[$index]['inventory_item_name'] = '';
    }

    public function createRequest(StockRequestService $service): void
    {
        $this->authorize('create', [OrderStockRequest::class, $this->order]);

        // Filter out empty items
        $items = collect($this->requestItems)
            ->filter(fn ($item) => ! empty($item['inventory_item_id']) && $item['qty_requested'] > 0)
            ->map(fn ($item) => [
                'inventory_item_id' => $item['inventory_item_id'],
                'qty_requested' => $item['qty_requested'],
                'note' => $item['note'] ?? null,
            ])
            ->values()
            ->toArray();

        if (empty($items)) {
            $this->addError('requestItems', 'Please add at least one item with a valid quantity.');

            return;
        }

        try {
            $service->createRequest(
                $this->order,
                $items,
                $this->requestNote ?: null,
                auth()->user()
            );

            $this->showNewRequestModal = false;
            session()->flash('success', 'Stock request created successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        } catch (\Exception $e) {
            $this->addError('requestItems', 'Failed to create request: '.$e->getMessage());
        }
    }

    public function viewRequest(int $requestId): void
    {
        $this->viewingRequestId = $this->viewingRequestId === $requestId ? null : $requestId;
    }

    public function render()
    {
        $user = auth()->user();

        // Get stock requests for this order
        $requests = OrderStockRequest::query()
            ->where('order_id', $this->order->id)
            ->with(['items.inventoryItem.stock', 'requester', 'handler'])
            ->latest()
            ->get();

        // Get available inventory items for the new request modal
        $inventoryItems = collect();
        if ($this->showNewRequestModal && $this->activeSearchIndex !== null) {
            $query = InventoryItem::query()
                ->where('is_active', true)
                ->with('stock');

            // Filter by search term if the user has typed something
            if (strlen($this->itemSearch) >= 1) {
                $search = $this->itemSearch;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            // Exclude items already selected in other rows
            $selectedIds = collect($this->requestItems)
                ->pluck('inventory_item_id')
                ->filter()
                ->values()
                ->toArray();

            if (! empty($selectedIds)) {
                $query->whereNotIn('id', $selectedIds);
            }

            $inventoryItems = $query->orderBy('name')->limit(20)->get();
        }

        // Can user create requests?
        $canCreate = $user->can('create', [OrderStockRequest::class, $this->order]);

        return view('livewire.orders.stock-requests.index', [
            'requests' => $requests,
            'inventoryItems' => $inventoryItems,
            'canCreate' => $canCreate,
        ])->title($this->getTitle());
    }
}

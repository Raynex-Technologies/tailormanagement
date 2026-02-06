<?php

namespace App\Livewire\Inventory\Stock;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Services\Inventory\StockMovementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Stock Overview')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public string $stockFilter = ''; // 'low' for low stock only

    public int $perPage = 15;

    // Receive Stock Modal
    public bool $showReceiveModal = false;
    public ?int $receiveItemId = null;
    public string $receiveItemName = '';
    public float $receiveQty = 1;
    public ?float $receiveUnitCost = null;
    public string $receiveNote = '';

    // Adjust Stock Modal
    public bool $showAdjustModal = false;
    public ?int $adjustItemId = null;
    public string $adjustItemName = '';
    public float $adjustCurrentQty = 0;
    public float $adjustQty = 0;
    public string $adjustNote = '';

    protected function receiveRules(): array
    {
        return [
            'receiveQty' => ['required', 'numeric', 'min:0.01'],
            'receiveUnitCost' => ['nullable', 'numeric', 'min:0'],
            'receiveNote' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function adjustRules(): array
    {
        return [
            'adjustQty' => ['required', 'numeric', 'not_in:0'],
            'adjustNote' => ['required', 'string', 'max:500'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStockFilter(): void
    {
        $this->resetPage();
    }

    // === RECEIVE STOCK ===

    public function openReceiveModal(int $id): void
    {
        $this->authorize('inventory.stock.receive');

        $item = InventoryItem::findOrFail($id);

        $this->receiveItemId = $item->id;
        $this->receiveItemName = "{$item->sku} - {$item->name}";
        $this->receiveQty = 1;
        $this->receiveUnitCost = $item->default_buy_price;
        $this->receiveNote = '';
        $this->showReceiveModal = true;
    }

    public function receiveStock(StockMovementService $service): void
    {
        $this->authorize('inventory.stock.receive');

        $this->validate($this->receiveRules());

        try {
            $item = InventoryItem::findOrFail($this->receiveItemId);

            $service->receive(
                item: $item,
                qty: $this->receiveQty,
                unitCost: $this->receiveUnitCost,
                note: $this->receiveNote ?: null,
                actor: auth()->user()
            );

            session()->flash('success', "Received {$this->receiveQty} {$item->unit} of {$item->name}.");
            $this->closeReceiveModal();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError("receive{$field}", $messages[0]);
            }
        }
    }

    public function closeReceiveModal(): void
    {
        $this->showReceiveModal = false;
        $this->reset(['receiveItemId', 'receiveItemName', 'receiveQty', 'receiveUnitCost', 'receiveNote']);
        $this->resetValidation();
    }

    // === ADJUST STOCK ===

    public function openAdjustModal(int $id): void
    {
        $this->authorize('inventory.stock.adjust');

        $item = InventoryItem::with('stock')->findOrFail($id);

        $this->adjustItemId = $item->id;
        $this->adjustItemName = "{$item->sku} - {$item->name}";
        $this->adjustCurrentQty = $item->stock?->qty_on_hand ?? 0;
        $this->adjustQty = 0;
        $this->adjustNote = '';
        $this->showAdjustModal = true;
    }

    public function adjustStock(StockMovementService $service): void
    {
        $this->authorize('inventory.stock.adjust');

        $this->validate($this->adjustRules());

        try {
            $item = InventoryItem::findOrFail($this->adjustItemId);

            $service->adjust(
                item: $item,
                qtyDelta: $this->adjustQty,
                note: $this->adjustNote,
                actor: auth()->user()
            );

            $direction = $this->adjustQty > 0 ? 'increased' : 'decreased';
            session()->flash('success', "Stock {$direction} by " . abs($this->adjustQty) . " for {$item->name}.");
            $this->closeAdjustModal();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError("adjust{$field}", $messages[0]);
            }
        }
    }

    public function closeAdjustModal(): void
    {
        $this->showAdjustModal = false;
        $this->reset(['adjustItemId', 'adjustItemName', 'adjustCurrentQty', 'adjustQty', 'adjustNote']);
        $this->resetValidation();
    }

    public function render()
    {
        // Get stock statistics
        $stats = $this->getStockStats();

        // Get stock items with their details
        $stocks = InventoryItem::query()
            ->with(['category', 'stock'])
            ->where('is_active', true)
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryFilter, fn ($q) => $q->where('inventory_category_id', $this->categoryFilter))
            ->when($this->stockFilter === 'low', fn ($q) => $q->whereHas('stock', function ($sq) {
                $sq->whereRaw('qty_on_hand <= inventory_items.reorder_level');
            }))
            ->orderByRaw('COALESCE((SELECT qty_on_hand FROM inventory_stocks WHERE inventory_stocks.inventory_item_id = inventory_items.id), 0) <= reorder_level DESC')
            ->orderBy('name')
            ->paginate($this->perPage);

        $categories = InventoryCategory::orderBy('name')->pluck('name', 'id');

        return view('livewire.inventory.stock.index', [
            'stocks' => $stocks,
            'categories' => $categories,
            'stats' => $stats,
        ]);
    }

    protected function getStockStats(): array
    {
        $totalItems = InventoryItem::where('is_active', true)->count();

        $lowStockCount = InventoryItem::where('is_active', true)
            ->whereHas('stock', function ($q) {
                $q->whereRaw('qty_on_hand <= inventory_items.reorder_level');
            })
            ->count();

        $totalOnHand = InventoryStock::sum('qty_on_hand');

        $recentMovements = InventoryTransaction::where('created_at', '>=', now()->subDays(7))->count();

        return [
            'total_items' => $totalItems,
            'low_stock_count' => $lowStockCount,
            'total_on_hand' => $totalOnHand,
            'recent_movements' => $recentMovements,
        ];
    }
}

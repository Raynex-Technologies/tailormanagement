<?php

namespace App\Livewire\Inventory\Items;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\Inventory\StockMovementService;
use App\Support\BranchContext;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Inventory Items')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public string $statusFilter = '';

    public int $perPage = 15;

    // Item Modal
    public bool $showItemModal = false;
    public bool $isEditing = false;
    public ?int $editingId = null;

    // Branch (for global admins)
    public ?int $branch_id = null;
    public bool $showBranchSelector = false;

    // Item form fields
    public string $sku = '';
    public string $name = '';
    public ?int $inventory_category_id = null;
    public string $unit = 'pcs';
    public int $reorder_level = 10;
    public ?float $default_buy_price = null;
    public ?float $default_sell_price = null;
    public bool $is_active = true;

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

    protected function rules(): array
    {
        $skuUniqueRule = $this->isEditing
            ? "unique:inventory_items,sku,{$this->editingId}"
            : 'unique:inventory_items,sku';

        $rules = [
            'sku' => ['required', 'string', 'max:50', $skuUniqueRule],
            'name' => ['required', 'string', 'max:255'],
            'inventory_category_id' => ['required', 'exists:inventory_categories,id'],
            'unit' => ['required', 'string', 'max:50'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'default_buy_price' => ['nullable', 'numeric', 'min:0'],
            'default_sell_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];

        // Global admins must select branch on create
        $user = auth()->user();
        if ($user->isGlobalAdmin() && ! $this->isEditing) {
            $rules['branch_id'] = ['required', 'exists:branches,id'];
        }

        return $rules;
    }

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

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('inventory.items.manage');

        $this->resetItemForm();
        $this->isEditing = false;

        // Initialize branch context
        $user = auth()->user();
        $this->showBranchSelector = $user->isGlobalAdmin();

        if ($user->isGlobalAdmin()) {
            $this->branch_id = BranchContext::id() ?? $user->branch_id;
        } else {
            $this->branch_id = $user->branch_id;
        }

        $this->showItemModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('inventory.items.manage');

        $item = InventoryItem::findOrFail($id);

        $this->editingId = $item->id;
        $this->sku = $item->sku;
        $this->name = $item->name;
        $this->inventory_category_id = $item->inventory_category_id;
        $this->unit = $item->unit;
        $this->reorder_level = $item->reorder_level;
        $this->default_buy_price = $item->default_buy_price;
        $this->default_sell_price = $item->default_sell_price;
        $this->is_active = $item->is_active;
        $this->isEditing = true;
        $this->showItemModal = true;
    }

    public function saveItem(): void
    {
        $this->authorize('inventory.items.manage');

        $this->validate();

        $user = auth()->user();

        $data = [
            'sku' => strtoupper($this->sku),
            'name' => $this->name,
            'inventory_category_id' => $this->inventory_category_id,
            'unit' => $this->unit,
            'reorder_level' => $this->reorder_level,
            'default_buy_price' => $this->default_buy_price,
            'default_sell_price' => $this->default_sell_price,
            'is_active' => $this->is_active,
        ];

        if ($this->isEditing) {
            $item = InventoryItem::findOrFail($this->editingId);
            $item->update($data);
            session()->flash('success', 'Item updated successfully.');
        } else {
            // Set branch_id for create
            if ($user->isGlobalAdmin()) {
                $data['branch_id'] = $this->branch_id;
            }

            $item = InventoryItem::create($data);

            // Ensure stock record exists
            $item->stock()->firstOrCreate([
                'inventory_item_id' => $item->id,
            ], [
                'branch_id' => $item->branch_id,
                'qty_on_hand' => 0,
                'qty_reserved' => 0,
            ]);

            session()->flash('success', 'Item created successfully.');
        }

        $this->closeItemModal();
    }

    public function closeItemModal(): void
    {
        $this->showItemModal = false;
        $this->resetItemForm();
        $this->resetValidation();
    }

    protected function resetItemForm(): void
    {
        $this->reset([
            'editingId',
            'sku',
            'name',
            'inventory_category_id',
            'unit',
            'reorder_level',
            'default_buy_price',
            'default_sell_price',
        ]);
        $this->is_active = true;
        $this->isEditing = false;
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('inventory.items.manage');

        $item = InventoryItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        session()->flash('success', $item->is_active ? 'Item activated.' : 'Item deactivated.');
    }

    // === RECEIVE STOCK ===

    public function openReceiveModal(int $id): void
    {
        $this->authorize('inventory.stock.receive');

        $item = InventoryItem::findOrFail($id);

        $this->receiveItemId = $item->id;
        $this->receiveItemName = $item->name;
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
        $this->adjustItemName = $item->name;
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
        $items = InventoryItem::query()
            ->with(['category', 'stock'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryFilter, fn ($q) => $q->where('inventory_category_id', $this->categoryFilter))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->statusFilter === 'low', fn ($q) => $q->whereHas('stock', function ($sq) {
                $sq->whereRaw('qty_on_hand <= inventory_items.reorder_level');
            }))
            ->orderBy('name')
            ->paginate($this->perPage);

        $categories = InventoryCategory::orderBy('name')->pluck('name', 'id');

        // Get branches for global admin selector
        $branches = auth()->user()->isGlobalAdmin()
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.inventory.items.index', [
            'items' => $items,
            'categories' => $categories,
            'branches' => $branches,
        ]);
    }
}

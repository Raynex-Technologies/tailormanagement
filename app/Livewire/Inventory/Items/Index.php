<?php

namespace App\Livewire\Inventory\Items;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Services\Inventory\StockMovementService;
use App\Services\Media\ImageUploadService;
use App\Support\BranchContext;
use App\Support\Livewire\NormalizesMoneyInputs;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Inventory Items')]
class Index extends Component
{
    use NormalizesMoneyInputs;
    use WithFileUploads, WithPagination;

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

    public ?int $inventory_unit_id = null;

    public int $reorder_level = 10;

    public string|float|null $default_buy_price = null;

    public string|float|null $default_sell_price = null;

    public bool $is_active = true;

    public array $itemImages = [];

    // Receive Stock Modal
    public bool $showReceiveModal = false;

    public ?int $receiveItemId = null;

    public string $receiveItemName = '';

    public float $receiveQty = 1;

    public string|float|null $receiveUnitCost = null;

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

        $skuRule = $this->isEditing
            ? ['required', 'string', 'max:100', $skuUniqueRule]
            : ['nullable', 'string', 'max:100', $skuUniqueRule];

        $rules = [
            'sku' => $skuRule,
            'name' => ['required', 'string', 'max:255'],
            'inventory_category_id' => ['required', 'exists:inventory_categories,id'],
            'inventory_unit_id' => ['required', 'exists:inventory_units,id'],
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
        $this->inventory_unit_id = $item->inventory_unit_id
            ?? InventoryUnit::query()->where('name', $item->unit)->value('id');
        $this->reorder_level = $item->reorder_level;
        $this->default_buy_price = $item->default_buy_price;
        $this->default_sell_price = $item->default_sell_price;
        $this->is_active = $item->is_active;
        $this->isEditing = true;
        $this->showItemModal = true;
    }

    public function saveItem(): void
    {
        $this->normalizeMoneyInputs();
        $this->authorize('inventory.items.manage');

        $user = auth()->user();
        $this->sku = Str::upper(trim($this->sku));

        if (! $this->isEditing && $this->sku === '') {
            $effectiveBranchId = $user->isGlobalAdmin() ? $this->branch_id : $user->branch_id;
            $this->sku = $this->generateAutoSku($effectiveBranchId, $this->name);
        }

        $this->validate();

        $unit = InventoryUnit::findOrFail($this->inventory_unit_id);

        $data = [
            'sku' => $this->sku,
            'name' => $this->name,
            'inventory_category_id' => $this->inventory_category_id,
            'inventory_unit_id' => $unit->id,
            'unit' => $unit->name,
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
            'inventory_unit_id',
            'reorder_level',
            'default_buy_price',
            'default_sell_price',
        ]);
        $this->is_active = true;
        $this->isEditing = false;
    }

    protected function generateAutoSku(?int $branchId, string $itemName): string
    {
        $branchPrefix = $this->branchSkuPrefix($branchId);
        $skuCode = $this->skuCodeFromName($itemName);
        $baseSku = "{$branchPrefix}-{$skuCode}";
        $candidate = $baseSku;
        $counter = 2;

        while (InventoryItem::withoutBranchScope()->where('sku', $candidate)->exists()) {
            $candidate = "{$baseSku}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    protected function branchSkuPrefix(?int $branchId): string
    {
        if (! $branchId) {
            return 'BRANCH';
        }

        $branch = Branch::query()->find($branchId);
        $code = (string) ($branch?->code ?? '');
        $normalized = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        return $normalized !== '' ? $normalized : 'BRANCH'.$branchId;
    }

    protected function skuCodeFromName(string $itemName): string
    {
        $tokens = preg_split('/[^A-Za-z0-9]+/', Str::upper($itemName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return 'ITEM';
        }

        $parts = [];
        foreach (array_slice($tokens, 0, 4) as $token) {
            $parts[] = substr($token, 0, 3);
        }

        return implode('-', array_filter($parts)) ?: 'ITEM';
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('inventory.items.manage');

        $item = InventoryItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        session()->flash('success', $item->is_active ? 'Item activated.' : 'Item deactivated.');
    }

    public function updatedItemImages(mixed $upload, string|int $itemId): void
    {
        $this->authorize('inventory.items.manage');

        $item = InventoryItem::query()->findOrFail((int) $itemId);

        $this->validate([
            "itemImages.{$itemId}" => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        try {
            $stored = app(ImageUploadService::class)->replacePublic(
                upload: $upload,
                existingPath: $item->featured_image_path,
                directory: 'inventory/items'
            );

            $item->update([
                'featured_image_path' => $stored->path,
            ]);

            unset($this->itemImages[$itemId]);
            session()->flash('success', "Image updated for {$item->name}.");
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
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
        $this->normalizeMoneyInputs();
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
            session()->flash('success', "Stock {$direction} by ".abs($this->adjustQty)." for {$item->name}.");
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
            ->with(['category', 'stock', 'inventoryUnit'])
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
        $units = InventoryUnit::orderBy('name')->pluck('name', 'id');

        // Get branches for global admin selector
        $branches = auth()->user()->isGlobalAdmin()
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.inventory.items.index', [
            'items' => $items,
            'categories' => $categories,
            'units' => $units,
            'branches' => $branches,
        ]);
    }
}

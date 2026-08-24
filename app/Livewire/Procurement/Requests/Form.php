<?php

namespace App\Livewire\Procurement\Requests;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\Scopes\BranchScope;
use App\Services\Procurement\PurchaseRequestService;
use App\Support\BranchContext;
use App\Support\Livewire\NormalizesMoneyInputs;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Form extends Component
{
    use AuthorizesRequests;
    use NormalizesMoneyInputs;

    public ?PurchaseRequest $purchaseRequest = null;

    public bool $isEdit = false;

    // Branch (for global admins)
    public ?int $branchId = null;

    public bool $showBranchSelector = false;

    public ?string $note = null;

    public array $items = [];

    // Product search (single search bar at top)
    public string $productSearch = '';

    public array $searchResults = [];

    public bool $showSearchDropdown = false;

    public ?string $branchChangeMessage = null;

    protected function rules(): array
    {
        $rules = [
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price_est' => ['nullable', 'numeric', 'min:0'],
        ];

        // Global admins must select branch on create
        $user = auth()->user();
        if ($user->isGlobalAdmin() && ! $this->isEdit) {
            $rules['branchId'] = ['required', 'exists:branches,id'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'branchId.required' => 'Please select a branch before adding items.',
        ];
    }

    public function mount(?PurchaseRequest $purchaseRequest = null): void
    {
        if ($purchaseRequest && $purchaseRequest->exists) {
            $this->authorize('update', $purchaseRequest);
            $this->purchaseRequest = $purchaseRequest->load('items.inventoryItem');
            $this->isEdit = true;
            $this->note = $purchaseRequest->note;
            $this->branchId = $purchaseRequest->branch_id;

            // Load existing items
            foreach ($purchaseRequest->items as $item) {
                $this->items[] = [
                    'id' => $item->id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'item_name' => $item->item_name,
                    'sku' => $item->inventoryItem?->sku,
                    'qty' => $item->qty,
                    'unit_price_est' => $item->unit_price_est,
                    'current_stock' => $item->inventoryItem?->stock?->qty_on_hand ?? 0,
                ];
            }
        } else {
            $this->authorize('procurement.request.create');
            $this->initializeBranchContext();
        }
    }

    /**
     * Initialize branch context for new purchase requests.
     */
    protected function initializeBranchContext(): void
    {
        $user = auth()->user();

        $this->showBranchSelector = $user->isGlobalAdmin();

        if ($user->isGlobalAdmin()) {
            $this->branchId = BranchContext::id() ?? $user->branch_id;
        } else {
            $this->branchId = $user->branch_id;
        }
    }

    /**
     * Get the effective branch ID for inventory search.
     */
    protected function getSearchBranchId(): ?int
    {
        $user = auth()->user();

        // For non-global users, use their branch
        if (! $user->isGlobalAdmin()) {
            return $user->branch_id;
        }

        // For global admins, use selected branch or context
        return $this->branchId ?? BranchContext::id();
    }

    /**
     * Update search results when productSearch changes.
     */
    public function updatedProductSearch(): void
    {
        $this->resetValidation('items');
        $this->searchInventory();
    }

    /**
     * Update branch ID and clear search when branch changes.
     */
    public function updatedBranchId(): void
    {
        $this->branchChangeMessage = null;
        $this->searchResults = [];
        $this->productSearch = '';
        $this->showSearchDropdown = false;

        if (empty($this->items)) {
            return;
        }

        $removedInventoryItems = collect($this->items)
            ->filter(fn (array $item) => ! empty($item['inventory_item_id']));

        if ($removedInventoryItems->isEmpty()) {
            return;
        }

        $this->items = collect($this->items)
            ->reject(fn (array $item) => ! empty($item['inventory_item_id']))
            ->values()
            ->toArray();

        $removedCount = $removedInventoryItems->count();
        $this->branchChangeMessage = trans_choice(
            '{1} Changing the branch removed 1 inventory item from this draft.|[2,*] Changing the branch removed :count inventory items from this draft.',
            $removedCount,
            ['count' => $removedCount]
        );
    }

    /**
     * Search inventory items by name or SKU within the selected branch.
     */
    public function searchInventory(): void
    {
        $searchTerm = trim($this->productSearch);

        if (strlen($searchTerm) < 2) {
            $this->searchResults = [];
            $this->showSearchDropdown = false;

            return;
        }

        $branchId = $this->getSearchBranchId();

        // If no branch selected (global admin without selection), show warning
        if ($branchId === null) {
            $this->searchResults = [];
            $this->showSearchDropdown = false;

            return;
        }

        // Query inventory items for the specific branch
        // IMPORTANT: Use withoutGlobalScope to bypass BranchScoped trait,
        // then manually filter by the target branch to avoid scope issues
        $this->searchResults = InventoryItem::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('sku', 'like', "%{$searchTerm}%");
            })
            ->with('stock')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'unit' => $item->unit,
                    'default_buy_price' => $item->default_buy_price ?? 0,
                    'current_stock' => $item->stock?->qty_on_hand ?? 0,
                    'reorder_level' => $item->reorder_level ?? 0,
                ];
            })
            ->toArray();

        $this->showSearchDropdown = count($this->searchResults) > 0;
    }

    /**
     * Select an inventory item from search results and add to items list.
     */
    public function selectProduct(int $inventoryItemId): void
    {
        $this->branchChangeMessage = null;

        $branchId = $this->getSearchBranchId();

        $invItem = InventoryItem::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->where('id', $inventoryItemId)
            ->with('stock')
            ->first();

        if (! $invItem) {
            return;
        }

        // Check if item is already in the list
        $existingIndex = collect($this->items)->search(function ($item) use ($inventoryItemId) {
            return $item['inventory_item_id'] === $inventoryItemId;
        });

        if ($existingIndex !== false) {
            // Item already exists, increment qty
            $this->items[$existingIndex]['qty'] = (float) $this->items[$existingIndex]['qty'] + 1;
        } else {
            // Add new item
            $this->items[] = [
                'id' => null,
                'inventory_item_id' => $invItem->id,
                'item_name' => $invItem->name,
                'sku' => $invItem->sku,
                'qty' => 1,
                'unit_price_est' => $invItem->default_buy_price ?? 0,
                'current_stock' => $invItem->stock?->qty_on_hand ?? 0,
            ];
        }

        // Clear search
        $this->productSearch = '';
        $this->searchResults = [];
        $this->showSearchDropdown = false;
    }

    /**
     * Add a manual item (not from inventory).
     */
    public function addManualItem(): void
    {
        $this->branchChangeMessage = null;

        $this->items[] = [
            'id' => null,
            'inventory_item_id' => null,
            'item_name' => '',
            'sku' => null,
            'qty' => 1,
            'unit_price_est' => 0,
            'current_stock' => null,
        ];
    }

    /**
     * Remove an item from the list.
     */
    public function removeItem(int $index): void
    {
        $this->branchChangeMessage = null;
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Update item quantity.
     */
    public function updateItemQty(int $index, $qty): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['qty'] = max(0.01, (float) $qty);
        }
    }

    /**
     * Close search dropdown.
     */
    public function closeSearchDropdown(): void
    {
        $this->showSearchDropdown = false;
    }

    /**
     * Save the purchase request.
     */
    public function save(PurchaseRequestService $service): void
    {
        $this->normalizeMoneyInputs();
        $this->validate();

        $user = auth()->user();

        // Ensure at least one item has a name
        $validItems = collect($this->items)->filter(fn ($item) => ! empty(trim($item['item_name'] ?? '')));
        if ($validItems->isEmpty()) {
            $this->addError('items', 'At least one item with a name is required.');

            return;
        }

        try {
            $data = [
                'note' => $this->note,
                'items' => $validItems->values()->toArray(),
            ];

            // Include branch_id for global admins on create
            if (! $this->isEdit && $user->isGlobalAdmin()) {
                $data['branch_id'] = $this->branchId;
            }

            if ($this->isEdit) {
                $pr = $service->updateDraft($this->purchaseRequest, $user, $data);
                session()->flash('success', 'Purchase request updated successfully.');
            } else {
                $pr = $service->createDraft($user, $data);
                session()->flash('success', "Purchase request {$pr->request_no} created successfully.");
            }

            $this->redirect(route('procurement.requests.show', $pr), navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to save: '.$e->getMessage());
        }
    }

    /**
     * Get estimated total for all items.
     */
    public function getLineTotalProperty(): float
    {
        return collect($this->items)->sum(function ($item) {
            return ((float) ($item['qty'] ?? 0)) * ((float) ($item['unit_price_est'] ?? 0));
        });
    }

    public function render()
    {
        // Get branches for global admin selector
        $branches = $this->showBranchSelector
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.procurement.requests.form', [
            'lineTotal' => $this->lineTotal,
            'branches' => $branches,
            'canSearch' => $this->branchId !== null,
        ])->title($this->isEdit ? __('Edit Purchase Request') : __('New Purchase Request'));
    }
}

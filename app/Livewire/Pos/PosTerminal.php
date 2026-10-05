<?php

namespace App\Livewire\Pos;

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\PosSale;
use App\Services\Pos\PosSaleService;
use App\Support\BranchContext;
use App\Support\Livewire\NormalizesMoneyInputs;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class PosTerminal extends Component
{
    use \App\Livewire\Concerns\ValidatesPhoneNumbers;
    use AuthorizesRequests;
    use NormalizesMoneyInputs;

    public string $itemSearch = '';

    public bool $showVariationModal = false;

    #[\Livewire\Attributes\Locked]
    public ?int $variationItemId = null;

    public array $variationChoices = [];

    public string $customerSearch = '';

    public ?int $customerId = null;

    public string $selectedCustomerName = '';

    /** @var array<int, array{id:int, sku:?string, name:string, price:float, quantity:float, stock:float, discount_amount:float}> */
    public array $cart = [];

    public string|float $discountAmount = 0;

    public string|float $taxAmount = 0;

    public string|float $amountPaid = 0;

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public string $notes = '';

    public bool $showCustomerModal = false;

    public string $newCustomerName = '';

    public ?string $newCustomerPhone = null;

    public ?string $newCustomerEmail = null;

    public ?string $newCustomerAddress = null;

    public bool $showReceiptModal = false;

    public ?int $completedSaleId = null;

    public function mount(): void
    {
        $this->authorize('pos.view');
    }

    public function updatedItemSearch(): void
    {
        $this->resetErrorBag('cart');
    }

    public function updatedDiscountAmount(): void
    {
        $this->normalizeMoneyInputProperty('discountAmount');
        $this->syncAmountPaidToTotal();
    }

    public function updatedTaxAmount(): void
    {
        $this->normalizeMoneyInputProperty('taxAmount');
        $this->syncAmountPaidToTotal();
    }

    public function addFirstSearchMatch(?string $scan = null): void
    {
        $this->authorize('pos.sell');
        if ($scan !== null) {
            $this->itemSearch = $scan;
        }
        $resolver = app(\App\Services\Inventory\StockUnitResolver::class);
        $unit = $resolver->byBarcode($this->itemSearch) ?? $resolver->bySku($this->itemSearch);
        if ($unit) {
            $this->addStockUnit($unit->id);
            $this->itemSearch = '';
            $this->dispatch('pos-scan-ready');

            return;
        }
        // Known but unavailable identities must not fall through to a different product.
        $known = \App\Models\InventoryStockUnit::where('sku', $this->itemSearch)->exists() || \App\Models\InventoryStockUnitBarcode::where('barcode', $this->itemSearch)->exists();
        $this->addError('itemSearch', $known ? 'This exact SKU or barcode is unavailable in this branch. No item was added.' : 'No exact barcode or SKU match. Choose a product from the search results.');
    }

    public function addItem(int $itemId): void
    {
        $this->authorize('pos.sell');
        $item = InventoryItem::query()->whereKey($itemId)->where('branch_id', BranchContext::getEffectiveBranchId())->where('is_active', true)->firstOrFail();
        if ($item->variant_mode === 'variants') {
            $this->variationItemId = $item->id;
            $this->variationChoices = [];
            $this->showVariationModal = true;
            $this->resetErrorBag();

            return;
        }
        $this->addStockUnit(app(\App\Services\Inventory\StockUnitResolver::class)->forItem($item)->id);
    }

    public function chooseVariationValue(int $optionId, int $valueId): void
    {
        $this->authorize('pos.sell');
        $item = $this->variationProduct();
        $item->options()->findOrFail($optionId)->values()->findOrFail($valueId);
        // Later choices are deliberately cleared when an earlier choice changes.
        $keep = true;
        foreach ($item->options as $option) {
            if ($option->id === $optionId) {
                $keep = false;
            } if (! $keep) {
                unset($this->variationChoices['option_'.$option->id]);
            }
        }
        $this->variationChoices['option_'.$optionId] = $valueId;
    }

    protected function variationProduct(): InventoryItem
    {
        return InventoryItem::query()->where('branch_id', BranchContext::getEffectiveBranchId())->where('variant_mode', 'variants')->where('is_active', true)->with(['options.values', 'variants.selectedValues.option', 'variants.stockUnit.stock'])->findOrFail($this->variationItemId);
    }

    public function addSelectedVariation(): void
    {
        $item = $this->variationProduct();
        $ids = array_map('intval', array_values($this->variationChoices));
        sort($ids);
        $matches = $item->variants->filter(function ($v) use ($ids) {
            $selected = $v->selectedValues->pluck('id')->sort()->values()->all();

            return $ids === $selected;
        });
        if ($matches->count() !== 1 || ! $ids) {
            $this->addError('cart', 'Choose an exact offered variation.');

            return;
        }
        $this->addStockUnit($matches->first()->stockUnit->id);
    }

    public function addStockUnit(int $unitId): void
    {
        $this->authorize('pos.sell');
        $unit = \App\Models\InventoryStockUnit::with(['item', 'variant.selectedValues.option', 'stock'])->findOrFail($unitId);
        if (! app(\App\Services\Inventory\StockUnitResolver::class)->isSellable($unit) || (int) $unit->item->branch_id !== (int) BranchContext::getEffectiveBranchId() || $unit->selling_price === null) {
            $this->addError('cart', 'This product or variation is unavailable or needs pricing.');

            return;
        }
        $available = max(0, (float) (($unit->stock?->qty_on_hand ?? 0) - ($unit->stock?->qty_reserved ?? 0)));
        $key = 'unit_'.$unit->id;
        $quantity = ($this->cart[$key]['quantity'] ?? 0) + 1;
        if ($quantity > $available) {
            $this->addError('cart', 'This variation no longer has enough stock available.');

            return;
        }
        $this->cart[$key] = ['id' => $unit->id, 'inventory_item_id' => $unit->inventory_item_id, 'inventory_stock_unit_id' => $unit->id, 'sku' => $unit->sku, 'name' => $unit->item->name, 'variation' => $unit->variant?->display_name, 'price' => (float) $unit->selling_price, 'quantity' => $quantity, 'stock' => $available, 'discount_amount' => $this->cart[$key]['discount_amount'] ?? 0];
        $this->showVariationModal = false;
        $this->syncAmountPaidToTotal();
    }

    public function increaseQty(int $itemId): void
    {
        $itemId = 'unit_'.$itemId;
        if (! isset($this->cart[$itemId])) {
            return;
        }

        if ($this->cart[$itemId]['quantity'] + 1 > $this->cart[$itemId]['stock']) {
            $this->addError("cart.{$itemId}.quantity", 'Quantity cannot exceed available stock.');

            return;
        }

        $this->cart[$itemId]['quantity']++;
        $this->syncAmountPaidToTotal();
    }

    public function decreaseQty(int $itemId): void
    {
        $itemId = 'unit_'.$itemId;
        if (! isset($this->cart[$itemId])) {
            return;
        }

        $this->cart[$itemId]['quantity'] = max(1, $this->cart[$itemId]['quantity'] - 1);
        $this->syncAmountPaidToTotal();
    }

    public function updateQty(int $itemId, mixed $quantity): void
    {
        $itemId = 'unit_'.$itemId;
        if (! isset($this->cart[$itemId])) {
            return;
        }

        $quantity = (float) $quantity;

        if ($quantity <= 0) {
            $this->addError("cart.{$itemId}.quantity", 'Quantity must be greater than zero.');

            return;
        }

        if ($quantity > $this->cart[$itemId]['stock']) {
            $this->addError("cart.{$itemId}.quantity", 'Quantity cannot exceed available stock.');
            $quantity = $this->cart[$itemId]['stock'];
        }

        $this->cart[$itemId]['quantity'] = $quantity;
        $this->syncAmountPaidToTotal();
    }

    public function removeItem(int $itemId): void
    {
        $itemId = 'unit_'.$itemId;
        unset($this->cart[$itemId]);
        $this->syncAmountPaidToTotal();
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->reset(['discountAmount', 'taxAmount', 'amountPaid', 'paymentReference', 'notes']);
        $this->paymentMethod = 'cash';
        $this->resetErrorBag();
    }

    public function selectCustomer(int $customerId): void
    {
        $customer = Customer::query()->findOrFail($customerId);

        $this->customerId = $customer->id;
        $this->selectedCustomerName = $customer->name;
        $this->customerSearch = '';
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->selectedCustomerName = '';
    }

    public function openCustomerModal(): void
    {
        $this->authorize('pos.sell');
        $this->authorize('customers.create');
        $this->resetCustomerForm();
        $this->showCustomerModal = true;
    }

    public function createCustomer(): void
    {
        $this->authorize('pos.sell');
        $this->authorize('customers.create');

        $branchId = BranchContext::getEffectiveBranchId();

        $validated = $this->validate([
            'newCustomerName' => ['required', 'string', 'max:255'],
            'newCustomerPhone' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers', 'phone')->where(fn ($query) => $query->where('branch_id', $branchId)),
                new \App\Rules\AvailableCustomerPhone($branchId),
            ],
            'newCustomerEmail' => ['nullable', 'email', 'max:255'],
            'newCustomerAddress' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = Customer::create([
            'branch_id' => $branchId,
            'name' => $validated['newCustomerName'],
            'phone' => $validated['newCustomerPhone'] ?: null,
            'email' => $validated['newCustomerEmail'] ?: null,
            'address' => $validated['newCustomerAddress'] ?: null,
        ]);

        $this->selectCustomer($customer->id);
        $this->showCustomerModal = false;
        $this->resetCustomerForm();
        session()->flash('success', 'Customer created and selected.');
    }

    public function completeSale(PosSaleService $service): mixed
    {
        $this->normalizeMoneyInputs();
        $this->authorize('pos.sell');

        $this->validate([
            'discountAmount' => ['nullable', 'numeric', 'min:0'],
            'taxAmount' => ['nullable', 'numeric', 'min:0'],
            'amountPaid' => ['required', 'numeric', 'min:0'],
            'paymentMethod' => ['required', Rule::in(['cash', 'mobile_money', 'card', 'bank_transfer', 'other'])],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $sale = $service->complete(
                cart: collect($this->cart)->map(fn ($line) => [
                    'inventory_item_id' => $line['inventory_item_id'],
                    'inventory_stock_unit_id' => $line['inventory_stock_unit_id'],
                    'expected_price' => $line['price'],
                    'quantity' => $line['quantity'],
                    'discount_amount' => $line['discount_amount'] ?? 0,
                ])->values()->all(),
                cashier: auth()->user(),
                customerId: $this->customerId,
                discountAmount: $this->discountAmount,
                taxAmount: $this->taxAmount,
                amountPaid: $this->amountPaid,
                paymentMethod: $this->paymentMethod,
                paymentReference: $this->paymentReference ?: null,
                notes: $this->notes ?: null,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return null;
        }

        session()->flash('success', "Sale {$sale->sale_number} completed.");

        $this->dispatch('open-pos-receipt', url: route('pos.sales.show', $sale));
        $this->completedSaleId = $sale->id;
        $this->showReceiptModal = true;
        $this->resetSaleForm();

        return null;
    }

    public function getSubtotalProperty(): float
    {
        return collect($this->cart)->sum(fn ($line) => max(0, ($line['price'] * $line['quantity']) - ($line['discount_amount'] ?? 0)));
    }

    public function getTotalProperty(): float
    {
        return max(0, $this->subtotal - $this->discountAmount + $this->taxAmount);
    }

    public function getChangeDueProperty(): float
    {
        $amountPaid = (float) (get_object_vars($this)['amountPaid'] ?? 0);

        return max(0, $amountPaid - $this->total);
    }

    public function render()
    {
        $items = $this->searchItemsQuery()->limit(60)->get();

        $customers = Customer::query()
            ->when($this->customerSearch, fn ($query) => $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->customerSearch}%")
                    ->orWhere('phone', 'like', "%{$this->customerSearch}%")
                    ->orWhere('code', 'like', "%{$this->customerSearch}%");
            }))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'phone', 'code']);

        return view('livewire.pos.pos-terminal', [
            'items' => $items,
            'variationProduct' => $this->showVariationModal ? $this->variationProduct() : null,
            'customers' => $customers,
            'completedSale' => $this->completedSale(),
            'businessSettings' => BusinessSetting::instance(),
            'receiptQrCodeSvg' => $this->receiptQrCodeSvg(),
        ]);
    }

    protected function searchItemsQuery()
    {
        return InventoryItem::query()
            ->with(['category'])
            ->withSum(['physicalStocks as pos_on_hand'], 'qty_on_hand')
            ->withSum(['physicalStocks as pos_reserved'], 'qty_reserved')
            ->withMin(['stockUnits as pos_min_price' => fn ($q) => $q->where('is_active', true)->where('allocation_status', 'ready')], 'selling_price')
            ->withMax(['stockUnits as pos_max_price' => fn ($q) => $q->where('is_active', true)->where('allocation_status', 'ready')], 'selling_price')
            ->withCount(['stockUnits as pos_variation_count' => fn ($q) => $q->whereNotNull('inventory_item_variant_id')->where('is_active', true)->where('allocation_status', 'ready')])
            ->where('branch_id', BranchContext::getEffectiveBranchId())->where('is_active', true)
            ->when($this->itemSearch, fn ($query) => $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->itemSearch}%")
                    ->orWhere('sku', 'like', "%{$this->itemSearch}%")
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$this->itemSearch}%")->orWhere('name', 'like', "%{$this->itemSearch}%")->orWhereHas('selectedValues', fn ($values) => $values->where('name', 'like', "%{$this->itemSearch}%")))
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$this->itemSearch}%"));
            }))
            ->orderBy('name');
    }

    protected function availableStock(InventoryItem $item): float
    {
        return max(0, (float) (($item->stock?->qty_on_hand ?? 0) - ($item->stock?->qty_reserved ?? 0)));
    }

    protected function resetCustomerForm(): void
    {
        $this->reset(['newCustomerName', 'newCustomerPhone', 'newCustomerEmail', 'newCustomerAddress']);
        $this->resetValidation();
    }

    protected function resetSaleForm(): void
    {
        $this->cart = [];
        $this->customerId = null;
        $this->selectedCustomerName = '';
        $this->customerSearch = '';
        $this->itemSearch = '';
        $this->discountAmount = 0;
        $this->taxAmount = 0;
        $this->amountPaid = 0;
        $this->paymentMethod = 'cash';
        $this->paymentReference = '';
        $this->notes = '';
        $this->resetErrorBag();
    }

    protected function syncAmountPaidToTotal(): void
    {
        $this->amountPaid = $this->total;
    }

    protected function completedSale(): ?PosSale
    {
        if (! $this->completedSaleId) {
            return null;
        }

        return PosSale::query()
            ->with(['items.inventoryItem', 'customer', 'user', 'branch'])
            ->find($this->completedSaleId);
    }

    protected function receiptQrCodeSvg(): ?string
    {
        $sale = $this->completedSale();
        $url = $sale?->public_receipt_url;

        if (! $url) {
            return null;
        }

        $renderer = new ImageRenderer(
            new RendererStyle(150),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($url);
    }
}

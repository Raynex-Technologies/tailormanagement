<?php

namespace App\Livewire\Pos;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Services\Pos\PosSaleService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class PosTerminal extends Component
{
    use AuthorizesRequests;

    public string $itemSearch = '';

    public string $customerSearch = '';

    public ?int $customerId = null;

    public string $selectedCustomerName = '';

    /** @var array<int, array{id:int, sku:?string, name:string, price:float, quantity:float, stock:float, discount_amount:float}> */
    public array $cart = [];

    public float $discountAmount = 0;

    public float $taxAmount = 0;

    public float $amountPaid = 0;

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public string $notes = '';

    public bool $showCustomerModal = false;

    public string $newCustomerName = '';

    public ?string $newCustomerPhone = null;

    public ?string $newCustomerEmail = null;

    public ?string $newCustomerAddress = null;

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
        $this->syncAmountPaidToTotal();
    }

    public function updatedTaxAmount(): void
    {
        $this->syncAmountPaidToTotal();
    }

    public function addFirstSearchMatch(): void
    {
        $item = $this->searchItemsQuery()->first();

        if (! $item) {
            $this->addError('itemSearch', 'No matching in-stock item was found.');

            return;
        }

        $this->addItem($item->id);
        $this->itemSearch = '';
    }

    public function addItem(int $itemId): void
    {
        $this->authorize('pos.sell');

        $item = InventoryItem::query()->with('stock')->whereKey($itemId)->where('is_active', true)->firstOrFail();
        $available = $this->availableStock($item);

        if ($available <= 0) {
            $this->addError('cart', "{$item->name} is out of stock.");

            return;
        }

        if (isset($this->cart[$item->id])) {
            $this->increaseQty($item->id);

            return;
        }

        $this->cart[$item->id] = [
            'id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'price' => (float) ($item->default_sell_price ?? 0),
            'quantity' => 1,
            'stock' => $available,
            'discount_amount' => 0,
        ];

        $this->syncAmountPaidToTotal();
    }

    public function increaseQty(int $itemId): void
    {
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
        if (! isset($this->cart[$itemId])) {
            return;
        }

        $this->cart[$itemId]['quantity'] = max(1, $this->cart[$itemId]['quantity'] - 1);
        $this->syncAmountPaidToTotal();
    }

    public function updateQty(int $itemId, mixed $quantity): void
    {
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
        $this->resetCustomerForm();
        $this->showCustomerModal = true;
    }

    public function createCustomer(): void
    {
        $this->authorize('pos.sell');

        $branchId = BranchContext::getEffectiveBranchId();

        $validated = $this->validate([
            'newCustomerName' => ['required', 'string', 'max:255'],
            'newCustomerPhone' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers', 'phone')->where(fn ($query) => $query->where('branch_id', $branchId)),
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
                    'inventory_item_id' => $line['id'],
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
            'customers' => $customers,
        ]);
    }

    protected function searchItemsQuery()
    {
        return InventoryItem::query()
            ->with(['category', 'stock'])
            ->where('is_active', true)
            ->when($this->itemSearch, fn ($query) => $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->itemSearch}%")
                    ->orWhere('sku', 'like', "%{$this->itemSearch}%")
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
}

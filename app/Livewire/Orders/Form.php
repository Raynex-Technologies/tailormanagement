<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Events\OrderPaymentRecorded;
use App\Enums\Priority;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderMeasurement;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Form extends Component
{
    public ?Order $order = null;
    public bool $isEdit = false;

    // Branch (for global admins)
    public ?int $branch_id = null;
    public bool $showBranchSelector = false;
    public bool $mustSelectBranch = false;

    // Customer
    public ?int $customer_id = null;
    public string $customerSearch = '';
    public bool $showCustomerDropdown = false;
    public bool $showNewCustomerForm = false;

    // New customer fields
    #[Validate('required_if:showNewCustomerForm,true|max:191', as: 'customer name')]
    public string $newCustomerName = '';

    #[Validate('required_if:showNewCustomerForm,true|max:20', as: 'phone')]
    public string $newCustomerPhone = '';

    #[Validate('nullable|email|max:191', as: 'email')]
    public string $newCustomerEmail = '';

    #[Validate('nullable|max:500', as: 'address')]
    public string $newCustomerAddress = '';

    // Order details
    #[Validate('required|date|before_or_equal:today', as: 'order date')]
    public string $order_date = '';

    #[Validate('nullable|date|after_or_equal:today', as: 'due date')]
    public ?string $due_date = null;

    public string $priority = 'normal';

    #[Validate('nullable|max:1000', as: 'notes')]
    public string $notes = '';

    public ?int $assigned_tailor_id = null;

    #[Validate('nullable|numeric|min:0', as: 'discount')]
    public ?float $discount = 0;

    // Order lines
    public array $lines = [];

    // Deposit (create only, optional)
    #[Validate('nullable|numeric|min:0', as: 'deposit amount')]
    public ?float $deposit_amount = null;

    #[Validate('nullable|integer|exists:payment_methods,id', as: 'payment method')]
    public ?int $deposit_payment_method_id = null;

    #[Validate('nullable|string|max:100', as: 'reference')]
    public ?string $deposit_reference = null;

    // Computed totals
    public float $subtotal = 0;
    public float $total = 0;

    public function mount(?Order $order = null): void
    {
        if ($order && $order->exists) {
            $this->authorize('update', $order);
            $this->order = $order->load(['customer', 'lines.measurement', 'assignedTailor']);
            $this->isEdit = true;
            $this->branch_id = $order->branch_id;
            $this->fillFromOrder();
        } else {
            $this->authorize('create', Order::class);
            $this->initializeBranchContext();
            $this->order_date = now()->toDateString();
            $this->deposit_payment_method_id = $this->getDefaultPaymentMethodId();
            $this->addLine();
        }
    }

    protected function getDefaultPaymentMethodId(): ?int
    {
        return PaymentMethod::query()->whereKey(1)->value('id')
            ?? PaymentMethod::query()->orderBy('name')->value('id');
    }

    /**
     * Initialize branch context for new orders.
     */
    protected function initializeBranchContext(): void
    {
        $user = auth()->user();

        // Only global admins see the branch selector
        $this->showBranchSelector = $user->isGlobalAdmin();

        if ($user->isGlobalAdmin()) {
            // Default to current context or user's branch
            $this->branch_id = BranchContext::id() ?? $user->branch_id;

            // Must select if no default available
            $this->mustSelectBranch = $this->branch_id === null;
        } else {
            // Branch-tied users: auto-set, no selector
            $this->branch_id = $user->branch_id;
            $this->showBranchSelector = false;
            $this->mustSelectBranch = false;
        }
    }

    protected function fillFromOrder(): void
    {
        $this->customer_id = $this->order->customer_id;
        $this->customerSearch = $this->order->customer?->name ?? '';
        $this->order_date = $this->order->order_date?->format('Y-m-d')
            ?? $this->order->created_at?->format('Y-m-d')
            ?? now()->toDateString();
        $this->due_date = $this->order->due_date?->format('Y-m-d');
        $this->priority = $this->order->priority?->value ?? 'normal';
        $this->notes = $this->order->notes ?? '';
        $this->assigned_tailor_id = $this->order->assigned_tailor_id;
        $this->discount = (float) $this->order->discount;

        $this->lines = [];
        foreach ($this->order->lines as $line) {
            $measurements = $line->measurement?->measurements ?? [];
            $measurementPairs = [];

            foreach ($measurements as $key => $value) {
                $measurementPairs[] = ['key' => $key, 'value' => $value];
            }

            if (empty($measurementPairs)) {
                $measurementPairs[] = ['key' => '', 'value' => ''];
            }

            $this->lines[] = [
                'id' => $line->id,
                'item_name' => $line->item_name,
                'qty' => (float) $line->qty,
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->line_total,
                'notes' => $line->notes ?? '',
                'measurements' => $measurementPairs,
            ];
        }

        if (empty($this->lines)) {
            $this->addLine();
        }

        $this->calculateTotals();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'id' => null,
            'item_name' => '',
            'qty' => 1,
            'unit_price' => 0,
            'line_total' => 0,
            'notes' => '',
            'measurements' => [
                ['key' => '', 'value' => ''],
            ],
        ];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 1) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
            $this->calculateTotals();
        }
    }

    public function addMeasurement(int $lineIndex): void
    {
        $this->lines[$lineIndex]['measurements'][] = ['key' => '', 'value' => ''];
    }

    public function removeMeasurement(int $lineIndex, int $measurementIndex): void
    {
        if (count($this->lines[$lineIndex]['measurements']) > 1) {
            unset($this->lines[$lineIndex]['measurements'][$measurementIndex]);
            $this->lines[$lineIndex]['measurements'] = array_values($this->lines[$lineIndex]['measurements']);
        }
    }

    public function updatedLines(): void
    {
        $this->calculateTotals();
    }

    public function updatedDiscount(): void
    {
        $this->calculateTotals();
    }

    public function updatedDepositAmount(): void
    {
        $this->calculateTotals();
        $amount = $this->deposit_amount === null || $this->deposit_amount === '' ? 0 : (float) $this->deposit_amount;
        if ($amount > $this->total) {
            $this->deposit_amount = $this->total;
        }
    }

    protected function calculateTotals(): void
    {
        $this->subtotal = 0;

        foreach ($this->lines as $index => $line) {
            $qty = (float) ($line['qty'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $lineTotal = $qty * $unitPrice;

            $this->lines[$index]['line_total'] = $lineTotal;
            $this->subtotal += $lineTotal;
        }

        $this->total = $this->subtotal - ($this->discount ?? 0);
    }

    public function updatedCustomerSearch(): void
    {
        $this->showCustomerDropdown = strlen($this->customerSearch) >= 2;
        $this->customer_id = null;
    }

    public function selectCustomer(int $customerId): void
    {
        $branchId = $this->getEffectiveBranchIdForCustomerSearch();
        $customer = Customer::query()
            ->where('branch_id', $branchId)
            ->where('id', $customerId)
            ->first();
        if ($customer) {
            $this->customer_id = $customer->id;
            $this->customerSearch = $customer->name;
        }
        $this->showCustomerDropdown = false;
    }

    public function clearSelectedCustomer(): void
    {
        $this->customer_id = null;
        $this->customerSearch = '';
        $this->showCustomerDropdown = false;
    }

    public function toggleNewCustomerForm(): void
    {
        $this->showNewCustomerForm = ! $this->showNewCustomerForm;
        if ($this->showNewCustomerForm) {
            $this->customer_id = null;
            $this->customerSearch = '';
            $this->showCustomerDropdown = false;
        }
    }

    /**
     * Effective branch for customer search/create (current order branch or selected branch for new orders).
     */
    protected function getEffectiveBranchIdForCustomerSearch(): ?int
    {
        if ($this->isEdit && $this->order) {
            return $this->order->branch_id;
        }
        if (auth()->user()?->isGlobalAdmin()) {
            return $this->branch_id;
        }

        return auth()->user()?->branch_id;
    }

    public function getSelectedCustomerProperty(): ?Customer
    {
        if (! $this->customer_id) {
            return null;
        }
        $branchId = $this->getEffectiveBranchIdForCustomerSearch();

        return Customer::query()
            ->where('branch_id', $branchId)
            ->where('id', $this->customer_id)
            ->first();
    }

    public function getTitle(): string
    {
        return $this->isEdit ? "Edit Order {$this->order->order_no}" : 'Create Order';
    }

    public function save(): void
    {
        $user = auth()->user();

        // Build validation rules
        $rules = [
            'order_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'max:1000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'deposit_payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_name' => ['required', 'string', 'max:191'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];

        // Global admins must select branch if no context
        if ($user->isGlobalAdmin() && ! $this->isEdit) {
            $rules['branch_id'] = ['required', 'exists:branches,id'];
        }

        $this->validate($rules);

        // Must have customer
        if (! $this->customer_id && ! $this->showNewCustomerForm) {
            $this->addError('customer_id', 'Please select a customer or create a new one.');

            return;
        }

        if ($this->showNewCustomerForm) {
            $this->validate([
                'newCustomerName' => ['required', 'max:191'],
                'newCustomerPhone' => ['required', 'max:20'],
                'newCustomerEmail' => ['nullable', 'email', 'max:191'],
            ]);
        }

        // At least one line with item_name
        $hasValidLine = collect($this->lines)->some(fn ($line) => ! empty($line['item_name']));
        if (! $hasValidLine) {
            $this->addError('lines', 'At least one order line with an item name is required.');

            return;
        }

        $this->calculateTotals();

        // Deposit cannot exceed order total (create only)
        if (! $this->isEdit && $this->deposit_amount !== null && (float) $this->deposit_amount > 0) {
            if ((float) $this->deposit_amount > $this->total) {
                $this->addError('deposit_amount', __('Deposit cannot exceed order total.'));

                return;
            }

            if (! $this->deposit_payment_method_id) {
                $this->deposit_payment_method_id = $this->getDefaultPaymentMethodId();
            }

            if (! $this->deposit_payment_method_id) {
                $this->addError('deposit_payment_method_id', __('Please configure at least one payment method in Settings.'));

                return;
            }
        }

        // Determine effective branch_id for create
        $effectiveBranchId = $this->isEdit
            ? $this->order->branch_id
            : ($user->isGlobalAdmin() ? $this->branch_id : $user->branch_id);

        if (! $this->isEdit && ! $effectiveBranchId) {
            $this->addError('branch_id', 'Please select a branch to create this order.');

            return;
        }

        try {
            DB::transaction(function () use ($effectiveBranchId) {
                // Create customer if needed (with same branch)
                if ($this->showNewCustomerForm) {
                    $customer = Customer::create([
                        'branch_id' => $effectiveBranchId,
                        'name' => $this->newCustomerName,
                        'phone' => $this->newCustomerPhone,
                        'email' => $this->newCustomerEmail ?: null,
                        'address' => $this->newCustomerAddress ?: null,
                    ]);
                    $this->customer_id = $customer->id;
                }

                $this->calculateTotals();

                // Create or update order
                $orderData = [
                    'customer_id' => $this->customer_id,
                    'order_date' => $this->order_date ?: now()->toDateString(),
                    'due_date' => $this->due_date ?: null,
                    'priority' => Priority::from($this->priority),
                    'notes' => $this->notes ?: null,
                    'assigned_tailor_id' => $this->assigned_tailor_id ?: null,
                    'subtotal' => $this->subtotal,
                    'discount' => $this->discount ?? 0,
                    'total' => $this->total,
                ];

                if ($this->isEdit) {
                    $this->order->update($orderData);
                    $order = $this->order;
                } else {
                    $orderData['branch_id'] = $effectiveBranchId;
                    $orderData['status'] = OrderStatus::New;
                    $orderData['payment_status'] = PaymentStatus::Unpaid;
                    $orderData['created_by'] = auth()->id();
                    $order = Order::create($orderData);
                    event(new OrderCreated($order->load('customer'), auth()->user()));
                }

                // Handle lines
                $existingLineIds = [];

                foreach ($this->lines as $lineData) {
                    if (empty($lineData['item_name'])) {
                        continue;
                    }

                    $lineAttributes = [
                        'order_id' => $order->id,
                        'item_name' => $lineData['item_name'],
                        'qty' => $lineData['qty'],
                        'unit_price' => $lineData['unit_price'],
                        'line_total' => $lineData['line_total'],
                        'notes' => $lineData['notes'] ?? null,
                    ];

                    if (! empty($lineData['id'])) {
                        $line = OrderLine::find($lineData['id']);
                        if ($line && $line->order_id === $order->id) {
                            $line->update($lineAttributes);
                        } else {
                            $line = OrderLine::create($lineAttributes);
                        }
                    } else {
                        $line = OrderLine::create($lineAttributes);
                    }

                    $existingLineIds[] = $line->id;

                    // Handle measurements
                    $measurements = [];
                    foreach ($lineData['measurements'] ?? [] as $m) {
                        if (! empty($m['key'])) {
                            $measurements[$m['key']] = $m['value'] ?? '';
                        }
                    }

                    if (! empty($measurements)) {
                        OrderMeasurement::updateOrCreate(
                            ['order_line_id' => $line->id],
                            ['measurements' => $measurements]
                        );
                    } else {
                        // Remove measurement if empty
                        OrderMeasurement::where('order_line_id', $line->id)->delete();
                    }
                }

                // Delete removed lines
                if ($this->isEdit) {
                    $order->lines()->whereNotIn('id', $existingLineIds)->delete();
                }

                // Create deposit payment on new order if amount given
                if (! $this->isEdit && $this->deposit_amount !== null && (float) $this->deposit_amount > 0) {
                    $payment = OrderPayment::create([
                        'branch_id' => $order->branch_id,
                        'order_id' => $order->id,
                        'amount' => (float) $this->deposit_amount,
                        'payment_method_id' => $this->deposit_payment_method_id ?: $this->getDefaultPaymentMethodId(),
                        'reference' => $this->deposit_reference ?: null,
                        'paid_at' => now(),
                        'received_by' => auth()->id(),
                    ]);
                    $order->refresh();
                    $order->update(['payment_status' => $order->computed_payment_status]);
                    event(new OrderPaymentRecorded($order->fresh(), $payment, auth()->user()));
                }

                // Keep invoice aligned with current order details and lines.
                Invoice::syncFromOrder($order->fresh(['lines']), auth()->id());

                $this->order = $order;
            });

            session()->flash('success', $this->isEdit ? 'Order updated successfully.' : 'Order created successfully.');
            $this->redirect(route('orders.show', $this->order), navigate: true);

        } catch (\Exception $e) {
            $this->addError('save', 'Failed to save order: '.$e->getMessage());
        }
    }

    public function render()
    {
        $customers = [];
        $branchId = $this->getEffectiveBranchIdForCustomerSearch();
        if ($branchId && $this->showCustomerDropdown && strlen($this->customerSearch) >= 2) {
            $search = $this->customerSearch;
            $customers = Customer::query()
                ->where('branch_id', $branchId)
                ->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'phone', 'email', 'address', 'code']);
        }

        // Filter tailors by branch if a branch is selected
        $tailorsQuery = User::whereHas('roles', fn ($q) => $q->where('name', 'tailor'));
        if ($this->branch_id) {
            $tailorsQuery->where('branch_id', $this->branch_id);
        }
        $tailors = $tailorsQuery->orderBy('name')->get(['id', 'name']);

        $priorities = Priority::cases();
        $paymentMethods = PaymentMethod::query()
            ->orderByRaw('CASE WHEN id = 1 THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get(['id', 'name', 'account_number', 'account_holder_name']);

        // Get branches for global admin selector
        $branches = $this->showBranchSelector
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.orders.form', [
            'customers' => $customers,
            'selectedCustomer' => $this->selectedCustomer,
            'tailors' => $tailors,
            'priorities' => $priorities,
            'branches' => $branches,
            'paymentMethods' => $paymentMethods,
        ])->title($this->getTitle());
    }
}

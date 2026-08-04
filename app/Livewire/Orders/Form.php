<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Events\OrderCreated;
use App\Events\OrderPaymentRecorded;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderExpense;
use App\Models\OrderLine;
use App\Models\OrderMeasurement;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Form extends Component
{
    private const ORDER_EXPENSE_DESCRIPTIONS = [
        'Labour Charge',
        'Additional Materials',
        'Other',
    ];

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
    #[Validate('required|date', as: 'order date')]
    public string $order_date = '';

    #[Validate('nullable|date', as: 'due date')]
    public ?string $due_date = null;

    public string $priority = 'normal';

    #[Validate('nullable|max:1000', as: 'notes')]
    public string $notes = '';

    public ?int $assigned_tailor_id = null;

    #[Validate('nullable|numeric|min:0', as: 'discount')]
    public ?float $discount = 0;

    public array $order_expenses = [];

    // Order lines
    public array $lines = [];

    public bool $showInventoryPicker = false;

    public string $inventorySearch = '';

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
            $this->order = $order->load(['customer', 'lines.measurement', 'lines.assignedTailor', 'assignedTailor', 'orderExpenses']);
            $this->isEdit = true;
            $this->branch_id = $order->branch_id;
            $this->fillFromOrder();
        } else {
            $this->authorize('create', Order::class);
            $this->initializeBranchContext();
            $this->order_date = now()->toDateString();
            $this->deposit_payment_method_id = $this->getDefaultPaymentMethodId();
            $this->addLine();
            $this->syncOrderExpensesWithSelectedTailors();
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
                'inventory_item_id' => $line->inventory_item_id,
                'sku' => $line->sku,
                'assigned_tailor_id' => $line->assigned_tailor_id,
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

        $this->order_expenses = [];
        foreach ($this->order->orderExpenses->sortBy('id') as $expense) {
            $this->order_expenses[] = [
                'id' => $expense->id,
                'tailor_id' => $expense->tailor_id,
                'notes' => $expense->notes ?? '',
                'amount' => (float) $expense->amount,
            ];
        }

        $this->syncOrderExpensesWithSelectedTailors();

        $this->calculateTotals();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'id' => null,
            'inventory_item_id' => null,
            'sku' => null,
            'assigned_tailor_id' => null,
            'item_name' => '',
            'qty' => 1,
            'unit_price' => 0,
            'line_total' => 0,
            'notes' => '',
            'measurements' => [
                ['key' => '', 'value' => ''],
            ],
        ];

        $this->syncOrderExpensesWithSelectedTailors();
    }

    public function toggleInventoryPicker(): void
    {
        $this->showInventoryPicker = ! $this->showInventoryPicker;
    }

    public function addInventoryLine(int $inventoryItemId): void
    {
        $branchId = $this->getEffectiveBranchIdForInventory();

        if (! $branchId) {
            $this->addError('branch_id', 'Select a branch before adding inventory items.');

            return;
        }

        $item = InventoryItem::query()
            ->with('stock')
            ->whereKey($inventoryItemId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        if (! $item) {
            $this->addError('inventorySearch', 'Selected inventory item is not available for this branch.');

            return;
        }

        $newLine = [
            'id' => null,
            'inventory_item_id' => $item->id,
            'sku' => $item->sku,
            'assigned_tailor_id' => null,
            'item_name' => $item->name,
            'qty' => 1,
            'unit_price' => (float) ($item->default_sell_price ?? 0),
            'line_total' => (float) ($item->default_sell_price ?? 0),
            'notes' => '',
            'measurements' => [
                ['key' => '', 'value' => ''],
            ],
        ];

        if (count($this->lines) === 1
            && empty($this->lines[0]['id'])
            && empty($this->lines[0]['item_name'])
            && empty($this->lines[0]['inventory_item_id'])) {
            $this->lines[0] = $newLine;
        } else {
            $this->lines[] = $newLine;
        }

        $this->calculateTotals();
        $this->syncOrderExpensesWithSelectedTailors();
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 1) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
            $this->calculateTotals();
            $this->syncOrderExpensesWithSelectedTailors();
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

    public function addOrderExpense(): void
    {
        if (! empty($this->selectedExpenseTailorIds())) {
            $this->syncOrderExpensesWithSelectedTailors();

            return;
        }

        $this->normalizeOrderExpenses();
        $this->order_expenses[] = $this->emptyOrderExpenseRow();
    }

    public function removeOrderExpense(int $index): void
    {
        if (! empty($this->selectedExpenseTailorIds())) {
            $this->syncOrderExpensesWithSelectedTailors();

            return;
        }

        if (count($this->order_expenses) > 1) {
            unset($this->order_expenses[$index]);
            $this->order_expenses = array_values($this->order_expenses);

            return;
        }

        $this->order_expenses = [$this->emptyOrderExpenseRow()];
    }

    protected function normalizeOrderExpenses(): void
    {
        if (empty($this->order_expenses)) {
            $this->order_expenses = [$this->emptyOrderExpenseRow()];

            return;
        }

        $this->order_expenses = array_values(array_map(function ($expense) {
            return [
                'id' => isset($expense['id']) && $expense['id'] !== '' ? (int) $expense['id'] : null,
                'tailor_id' => isset($expense['tailor_id']) && $expense['tailor_id'] !== '' ? (int) $expense['tailor_id'] : null,
                'notes' => $this->normalizeOrderExpenseDescription($expense['notes'] ?? ''),
                'amount' => ($expense['amount'] ?? null) === '' ? null : ($expense['amount'] ?? null),
            ];
        }, $this->order_expenses));
    }

    protected function normalizeOrderExpenseDescription(mixed $description): string
    {
        $description = trim((string) $description);

        if ($description === '') {
            return '';
        }

        return in_array($description, self::ORDER_EXPENSE_DESCRIPTIONS, true)
            ? $description
            : 'Other';
    }

    protected function emptyOrderExpenseRow(?int $tailorId = null): array
    {
        return [
            'id' => null,
            'tailor_id' => $tailorId,
            'notes' => '',
            'amount' => null,
        ];
    }

    protected function isOrderExpenseRowFilled(array $expense): bool
    {
        $notes = trim((string) ($expense['notes'] ?? ''));
        $amount = $expense['amount'] ?? null;

        return $notes !== '' || ($amount !== null && $amount !== '');
    }

    protected function pickPreferredOrderExpenseRow(array $current, array $candidate): array
    {
        if ($this->isOrderExpenseRowFilled($candidate) && ! $this->isOrderExpenseRowFilled($current)) {
            return $candidate;
        }

        $currentId = (int) ($current['id'] ?? 0);
        $candidateId = (int) ($candidate['id'] ?? 0);

        if ($candidateId > 0 && $currentId <= 0) {
            return $candidate;
        }

        return $current;
    }

    /**
     * Selected tailor IDs for order expenses.
     * Order tailor overrides inline line-tailor assignments.
     *
     * @return array<int>
     */
    protected function selectedExpenseTailorIds(): array
    {
        if ($this->assigned_tailor_id) {
            return [(int) $this->assigned_tailor_id];
        }

        return $this->activeLineTailorIds()->all();
    }

    /**
     * Keep order_expenses as one row per unique selected tailor.
     * If no tailor is selected, keep a single unassigned row.
     */
    protected function syncOrderExpensesWithSelectedTailors(): void
    {
        $this->normalizeOrderExpenses();

        $selectedTailorIds = $this->selectedExpenseTailorIds();
        $rowsByTailor = [];
        $fallbackRows = [];

        foreach ($this->order_expenses as $expense) {
            $tailorId = (int) ($expense['tailor_id'] ?? 0);

            if ($tailorId > 0) {
                if (! isset($rowsByTailor[$tailorId])) {
                    $rowsByTailor[$tailorId] = $expense;

                    continue;
                }

                $preferred = $this->pickPreferredOrderExpenseRow($rowsByTailor[$tailorId], $expense);
                if ($preferred !== $rowsByTailor[$tailorId]) {
                    $fallbackRows[] = $rowsByTailor[$tailorId];
                    $rowsByTailor[$tailorId] = $preferred;
                } else {
                    $fallbackRows[] = $expense;
                }

                continue;
            }

            $fallbackRows[] = $expense;
        }

        if (! empty($selectedTailorIds)) {
            $syncedRows = [];

            foreach ($selectedTailorIds as $tailorId) {
                $row = $rowsByTailor[$tailorId]
                    ?? array_shift($fallbackRows)
                    ?? $this->emptyOrderExpenseRow($tailorId);

                $row['tailor_id'] = (int) $tailorId;
                $syncedRows[] = $row;
            }

            $this->order_expenses = array_values($syncedRows);

            return;
        }

        $fallbackRow = array_shift($fallbackRows);
        if (! $fallbackRow && ! empty($rowsByTailor)) {
            $fallbackRow = array_values($rowsByTailor)[0];
        }

        if (! $fallbackRow) {
            $this->order_expenses = [$this->emptyOrderExpenseRow()];

            return;
        }

        $fallbackRow['tailor_id'] = null;
        $this->order_expenses = [$fallbackRow];
    }

    protected function normalizeOrderTailorAssignment(): void
    {
        $tailorId = (int) ($this->assigned_tailor_id ?? 0);
        $this->assigned_tailor_id = $tailorId > 0 ? $tailorId : null;
    }

    protected function normalizeLineTailorAssignments(): void
    {
        $this->normalizeOrderTailorAssignment();

        if ($this->assigned_tailor_id) {
            $this->lines = array_values(array_map(function ($line) {
                $line['assigned_tailor_id'] = null;

                return $line;
            }, $this->lines));

            return;
        }

        $this->lines = array_values(array_map(function ($line) {
            $lineTailorId = (int) ($line['assigned_tailor_id'] ?? 0);
            $line['assigned_tailor_id'] = $lineTailorId > 0 ? $lineTailorId : null;

            return $line;
        }, $this->lines));
    }

    public function updatedAssignedTailorId($value): void
    {
        if ((int) $value <= 0) {
            $this->assigned_tailor_id = null;
            $this->syncOrderExpensesWithSelectedTailors();

            return;
        }

        $this->lines = array_values(array_map(function ($line) {
            $line['assigned_tailor_id'] = null;

            return $line;
        }, $this->lines));

        $this->syncOrderExpensesWithSelectedTailors();
    }

    public function updatedBranchId($value): void
    {
        $branchId = (int) ($value ?? 0);
        $this->branch_id = $branchId > 0 ? $branchId : null;
        $this->mustSelectBranch = $this->showBranchSelector && ! $this->isEdit && $this->branch_id === null;

        // Prevent stale cross-branch assignments after branch change.
        $this->assigned_tailor_id = null;
        $this->lines = array_values(array_map(function ($line) {
            $line['assigned_tailor_id'] = null;
            $line['inventory_item_id'] = null;
            $line['sku'] = null;

            return $line;
        }, $this->lines));

        $this->syncOrderExpensesWithSelectedTailors();
    }

    protected function hasFilledOrderExpenses(): bool
    {
        foreach ($this->order_expenses as $expense) {
            $notes = trim((string) ($expense['notes'] ?? ''));
            $amount = $expense['amount'] ?? null;

            if ($notes !== '' || ($amount !== null && $amount !== '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tailor IDs from non-empty order lines only.
     */
    protected function activeLineTailorIds(): \Illuminate\Support\Collection
    {
        return collect($this->lines)
            ->filter(fn ($line) => trim((string) ($line['item_name'] ?? '')) !== '')
            ->pluck('assigned_tailor_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    protected function validateOrderExpenses(): bool
    {
        $isValid = true;

        foreach ($this->order_expenses as $index => $expense) {
            $notes = trim((string) ($expense['notes'] ?? ''));
            $amount = $expense['amount'] ?? null;
            $hasNotes = $notes !== '';
            $hasAmount = $amount !== null && $amount !== '';

            if ($hasNotes && ! $hasAmount) {
                $this->addError("order_expenses.{$index}.amount", 'Amount is required when description is entered.');
                $isValid = false;
            }

            if ($hasAmount && ! $hasNotes) {
                $this->addError("order_expenses.{$index}.notes", 'Description is required when amount is entered.');
                $isValid = false;
            }

            if (($hasNotes || $hasAmount) && (int) ($expense['tailor_id'] ?? 0) <= 0) {
                $this->addError("order_expenses.{$index}.tailor_id", 'Select an order tailor or assign line tailor(s) before saving order expenses.');
                $isValid = false;
            }
        }

        return $isValid;
    }

    /**
     * Validate selected order-level and line-level tailor assignments.
     */
    protected function validateTailorAssignments(?int $branchId): bool
    {
        $selectedTailorIds = $this->activeLineTailorIds();

        if ($this->assigned_tailor_id) {
            $selectedTailorIds->push((int) $this->assigned_tailor_id);
        }

        $selectedTailorIds = $selectedTailorIds->unique()->values();

        if ($selectedTailorIds->isEmpty()) {
            return true;
        }

        $validTailorIds = User::query()
            ->whereIn('id', $selectedTailorIds->all())
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'tailor'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalidIds = $selectedTailorIds->reject(fn ($id) => in_array($id, $validTailorIds, true));

        if ($invalidIds->isEmpty()) {
            return true;
        }

        if ($this->assigned_tailor_id && $invalidIds->contains((int) $this->assigned_tailor_id)) {
            $this->addError('assigned_tailor_id', 'Selected order tailor must be a tailor in the same branch.');
        }

        foreach ($this->lines as $index => $line) {
            $lineTailorId = (int) ($line['assigned_tailor_id'] ?? 0);
            if ($lineTailorId > 0 && $invalidIds->contains($lineTailorId)) {
                $this->addError("lines.{$index}.assigned_tailor_id", 'Selected line tailor must be a tailor in the same branch.');
            }
        }

        return false;
    }

    public function updatedLines(): void
    {
        $this->calculateTotals();
        $this->syncOrderExpensesWithSelectedTailors();
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

    protected function getEffectiveBranchIdForTailorOptions(): ?int
    {
        if ($this->isEdit && $this->order) {
            return $this->order->branch_id;
        }

        if (auth()->user()?->isGlobalAdmin()) {
            return $this->branch_id;
        }

        return auth()->user()?->branch_id;
    }

    protected function getEffectiveBranchIdForInventory(): ?int
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
        $this->normalizeLineTailorAssignments();
        $this->syncOrderExpensesWithSelectedTailors();

        // Build validation rules
        $orderDateRules = ['required', 'date'];
        $dueDateRules = ['nullable', 'date'];

        if (! $this->allowsOrderDatesFlexibility()) {
            $orderDateRules[] = 'after_or_equal:today';
            $dueDateRules[] = 'after_or_equal:today';
        }

        $rules = [
            'order_date' => $orderDateRules,
            'due_date' => $dueDateRules,
            'notes' => ['nullable', 'max:1000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'deposit_payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'order_expenses' => ['nullable', 'array'],
            'order_expenses.*.tailor_id' => ['nullable', 'integer', 'exists:users,id'],
            'order_expenses.*.notes' => ['nullable', 'string', Rule::in(self::ORDER_EXPENSE_DESCRIPTIONS)],
            'order_expenses.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'assigned_tailor_id' => ['nullable', 'integer', 'exists:users,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'lines.*.assigned_tailor_id' => ['nullable', 'integer', 'exists:users,id'],
            'lines.*.item_name' => ['required', 'string', 'max:191'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];

        // Global admins must select branch if no context
        if ($user->isGlobalAdmin() && ! $this->isEdit) {
            $rules['branch_id'] = ['required', 'exists:branches,id'];
        }

        $this->validate($rules);

        // Determine effective branch_id for create
        $effectiveBranchId = $this->isEdit
            ? $this->order->branch_id
            : ($user->isGlobalAdmin() ? $this->branch_id : $user->branch_id);

        if (! $this->isEdit && ! $effectiveBranchId) {
            $this->addError('branch_id', 'Please select a branch to create this order.');

            return;
        }

        if (! $this->validateTailorAssignments($effectiveBranchId)) {
            return;
        }

        if (! $this->validateInventoryLines($effectiveBranchId)) {
            return;
        }

        if (! $this->validateOrderExpenses()) {
            return;
        }

        if ($this->hasFilledOrderExpenses() && empty($this->selectedExpenseTailorIds())) {
            $this->addError('order_expenses', 'Select an order tailor or assign line tailor(s) before saving order expenses.');

            return;
        }

        // Must have customer
        if (! $this->customer_id && ! $this->showNewCustomerForm) {
            $this->addError('customer_id', 'Please select a customer or create a new one.');

            return;
        }

        if ($this->showNewCustomerForm) {
            $this->validate([
                'newCustomerName' => ['required', 'max:191'],
                'newCustomerPhone' => [
                    'required',
                    'max:20',
                    Rule::unique('customers', 'phone')
                        ->where(fn ($query) => $query->where('branch_id', $effectiveBranchId)),
                ],
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
                }

                // Handle lines
                $existingLineIds = [];

                foreach ($this->lines as $lineData) {
                    if (empty($lineData['item_name'])) {
                        continue;
                    }

                    $lineAttributes = [
                        'order_id' => $order->id,
                        'inventory_item_id' => ($lineData['inventory_item_id'] ?? null) ?: null,
                        'sku' => $lineData['sku'] ?? null,
                        'assigned_tailor_id' => $lineData['assigned_tailor_id'] ?: null,
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
                    $this->syncInventoryStockForLine($line, (float) $lineData['qty']);

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
                    $removedLines = $order->lines()
                        ->whereNotIn('id', $existingLineIds)
                        ->get();

                    foreach ($removedLines as $removedLine) {
                        $this->returnIssuedInventoryForLine($removedLine);
                        $removedLine->delete();
                    }
                }

                $depositPayment = null;

                // Create deposit payment on new order if amount given
                if (! $this->isEdit && $this->deposit_amount !== null && (float) $this->deposit_amount > 0) {
                    $depositPayment = OrderPayment::create([
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
                    event(new OrderPaymentRecorded($order->fresh(), $depositPayment, auth()->user(), sendCustomerSms: false));
                }

                $existingExpenseIds = [];
                foreach ($this->order_expenses as $expenseData) {
                    $notes = trim((string) ($expenseData['notes'] ?? ''));
                    $amount = $expenseData['amount'] ?? null;
                    $isFilled = $notes !== '' || ($amount !== null && $amount !== '');

                    if (! $isFilled) {
                        continue;
                    }

                    $expenseId = (int) ($expenseData['id'] ?? 0);
                    if ($expenseId > 0) {
                        $existingExpense = OrderExpense::query()
                            ->where('order_id', $order->id)
                            ->find($expenseId);

                        if ($existingExpense) {
                            $existingExpense->update([
                                'tailor_id' => (int) ($expenseData['tailor_id'] ?? 0),
                                'amount' => (float) $amount,
                                'notes' => $notes ?: null,
                            ]);
                            $existingExpenseIds[] = $existingExpense->id;

                            continue;
                        }
                    }

                    $tailorIdForExpense = (int) ($expenseData['tailor_id'] ?? 0);

                    if ($tailorIdForExpense <= 0) {
                        continue;
                    }

                    $createdExpense = OrderExpense::create([
                        'order_id' => $order->id,
                        'tailor_id' => $tailorIdForExpense,
                        'amount' => (float) $amount,
                        'notes' => $notes ?: null,
                    ]);
                    $existingExpenseIds[] = $createdExpense->id;
                }

                if ($this->isEdit) {
                    $order->orderExpenses()
                        ->whereNotIn('id', $existingExpenseIds)
                        ->delete();
                }

                if (! $this->isEdit) {
                    event(new OrderCreated(
                        $order->fresh(['customer', 'lines']),
                        auth()->user(),
                        $depositPayment
                    ));
                }

                // Keep invoice aligned with current order details and lines.
                Invoice::syncFromOrder($order->fresh(['lines']), auth()->id());

                $this->order = $order;
            });

            session()->flash('success', $this->isEdit ? 'Order updated successfully.' : 'Order created successfully.');
            $this->redirect(route('orders.show', $this->order), navigate: true);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->addError('save', 'Failed to save order: '.$e->getMessage());
        }
    }

    protected function validateInventoryLines(int $branchId): bool
    {
        $inventoryItemIds = collect($this->lines)
            ->pluck('inventory_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($inventoryItemIds->isEmpty()) {
            return true;
        }

        $items = InventoryItem::query()
            ->with('stock')
            ->whereIn('id', $inventoryItemIds->all())
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $isValid = true;
        $requestedQtyByItem = [];
        $existingIssuedQtyByItem = [];

        foreach ($this->lines as $index => $line) {
            $itemId = (int) ($line['inventory_item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $item = $items->get($itemId);
            if (! $item) {
                $this->addError("lines.{$index}.inventory_item_id", 'Selected inventory item must belong to this order branch.');
                $isValid = false;

                continue;
            }

            $requestedQty = (float) ($line['qty'] ?? 0);
            $requestedQtyByItem[$itemId] = ($requestedQtyByItem[$itemId] ?? 0) + $requestedQty;

            $alreadyIssuedForLine = ! empty($line['id'])
                ? max(0, -1 * (float) InventoryTransaction::query()
                    ->where('reference_type', OrderLine::class)
                    ->where('reference_id', $line['id'])
                    ->where('inventory_item_id', $itemId)
                    ->sum('qty'))
                : 0.0;

            $existingIssuedQtyByItem[$itemId] = ($existingIssuedQtyByItem[$itemId] ?? 0) + $alreadyIssuedForLine;
        }

        foreach ($requestedQtyByItem as $itemId => $requestedQty) {
            $item = $items->get($itemId);
            if (! $item) {
                continue;
            }

            $available = (float) ($item->stock?->qty_on_hand ?? 0) + (float) ($existingIssuedQtyByItem[$itemId] ?? 0);

            if ($available < $requestedQty) {
                $this->addError('lines', "{$item->name} has only {$available} available.");
                $isValid = false;
            }
        }

        return $isValid;
    }

    protected function syncInventoryStockForLine(OrderLine $line, float $newQty): void
    {
        $this->returnIssuedInventoryForLine($line, 'inventory updated');

        if (! $line->inventory_item_id || $newQty <= 0) {
            return;
        }

        $item = InventoryItem::query()
            ->whereKey($line->inventory_item_id)
            ->where('branch_id', $line->order?->branch_id)
            ->firstOrFail();

        app(StockMovementService::class)->issue(
            item: $item,
            qty: $newQty,
            note: "Order {$line->order?->order_no}",
            actor: auth()->user(),
            reference: $line
        );
    }

    protected function returnIssuedInventoryForLine(OrderLine $line, string $reason = 'line removed'): void
    {
        $netIssuedByItem = InventoryTransaction::query()
            ->where('reference_type', OrderLine::class)
            ->where('reference_id', $line->id)
            ->selectRaw('inventory_item_id, SUM(qty) as net_qty')
            ->groupBy('inventory_item_id')
            ->get();

        foreach ($netIssuedByItem as $transaction) {
            $qtyToReturn = max(0, -1 * (float) $transaction->net_qty);

            if ($qtyToReturn <= 0) {
                continue;
            }

            $item = InventoryItem::query()->find($transaction->inventory_item_id);
            if (! $item) {
                continue;
            }

            app(StockMovementService::class)->return(
                item: $item,
                qty: $qtyToReturn,
                note: "Order {$line->order?->order_no} {$reason}",
                actor: auth()->user(),
                reference: $line
            );
        }
    }

    protected function allowsOrderDatesFlexibility(): bool
    {
        return (bool) BusinessSetting::instance()->allow_order_dates_flexibility;
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

        // Tailor options must always be branch-scoped.
        $tailorsQuery = User::whereHas('roles', fn ($q) => $q->where('name', 'tailor'));
        $tailorBranchId = $this->getEffectiveBranchIdForTailorOptions();
        if ($tailorBranchId) {
            $tailorsQuery->where('branch_id', $tailorBranchId);
        } else {
            $tailorsQuery->whereRaw('1 = 0');
        }
        $tailors = $tailorsQuery->orderBy('name')->get(['id', 'name']);

        $priorities = Priority::cases();
        $paymentMethods = PaymentMethod::query()
            ->orderByRaw('CASE WHEN id = 1 THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get(['id', 'name', 'account_number', 'account_holder_name']);

        $inventoryItems = collect();
        $inventoryBranchId = $this->getEffectiveBranchIdForInventory();
        if ($inventoryBranchId) {
            $inventoryItems = InventoryItem::query()
                ->with('stock')
                ->where('branch_id', $inventoryBranchId)
                ->where('is_active', true)
                ->when(trim($this->inventorySearch) !== '', function ($query) {
                    $search = trim($this->inventorySearch);
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'branch_id', 'sku', 'name', 'default_sell_price', 'unit']);
        }

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
            'inventoryItems' => $inventoryItems,
            'allowOrderDatesFlexibility' => $this->allowsOrderDatesFlexibility(),
        ])->title($this->getTitle());
    }
}

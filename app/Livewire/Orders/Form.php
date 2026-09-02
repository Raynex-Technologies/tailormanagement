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
use App\Models\MeasurementField;
use App\Models\Order;
use App\Models\OrderCatalogItem;
use App\Models\OrderExpense;
use App\Models\OrderLine;
use App\Models\OrderPackageInstance;
use App\Models\OrderPackageTemplate;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Customers\CustomerCreator;
use App\Services\Inventory\StockMovementService;
use App\Services\Measurements\CustomerMeasurementProfileService;
use App\Services\Orders\OrderCatalogCompositionService;
use App\Services\Orders\OrderMeasurementSavebackService;
use App\Services\Orders\OrderMeasurementService;
use App\Services\Orders\OrderPackagePricingService;
use App\Support\BranchContext;
use App\Support\Customers\CustomerAccess;
use App\Support\Livewire\NormalizesMoneyInputs;
use App\Support\Orders\OrderMeasurementSnapshot;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Form extends Component
{
    use NormalizesMoneyInputs;

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

    public bool $showNewCustomerModal = false;

    // New customer fields
    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerEmail = '';

    public string $newCustomerAddress = '';

    public bool $showMeasurementModal = false;

    public ?int $measurementModalLineIndex = null;

    /** @var array<string, mixed> */
    public array $measurementDraft = [];

    public bool $showCustomerMeasurementSavebackModal = false;

    /** @var array<string, mixed> */
    public array $customerMeasurementSaveback = [];

    /** @var array<int|string, string> */
    public array $customerMeasurementConflictChoices = [];

    public bool $saveCustomerMeasurementRevision = false;

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
    public string|float|null $discount = 0;

    public array $order_expenses = [];

    // Order lines
    public array $lines = [];

    public bool $showCatalogPicker = false;

    public string $catalogTab = 'packages';

    public string $catalogSearch = '';

    public bool $showDirectCatalogConfigurator = false;

    public ?int $selectedCatalogItemId = null;

    public string $directCatalogQuantity = '1';

    public bool $showPackageConfigurator = false;

    public ?string $configuringPackageKey = null;

    /** @var array<string, mixed> */
    public array $packageConfigurator = [];

    /** @var array<int|string, int|float|string> */
    public array $packageQuantities = [];

    /** @var array<string, array<string, mixed>> */
    public array $packages = [];

    public ?int $previousBranchId = null;

    public string $inventorySearch = '';

    // Deposit (create only, optional)
    #[Validate('nullable|numeric|min:0', as: 'deposit amount')]
    public string|float|null $deposit_amount = null;

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
            $this->order = $order->load(['customer', 'lines.measurement', 'lines.assignedTailor', 'packageInstances', 'assignedTailor', 'orderExpenses']);
            $this->isEdit = true;
            $this->branch_id = $order->branch_id;
            $this->previousBranchId = $order->branch_id;
            $this->fillFromOrder();
        } else {
            $this->authorize('create', Order::class);
            $this->initializeBranchContext();
            $this->previousBranchId = $this->branch_id;
            $this->order_date = now()->toDateString();
            $this->deposit_payment_method_id = $this->getDefaultPaymentMethodId();
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

        $this->packages = [];
        $packageKeysByInstance = [];
        foreach ($this->order->packageInstances as $instance) {
            $key = (string) Str::uuid();
            $originalComponents = $instance->original_component_snapshot ?: $instance->component_snapshot ?: [];
            $configuredComponents = $instance->configured_component_snapshot ?: $instance->component_snapshot ?: [];
            $originalSnapshot = [
                'template_id' => $instance->order_package_template_id,
                'template_code' => null,
                'revision' => $instance->source_template_revision,
                'name' => $instance->package_name,
                'description' => $instance->package_description,
                'cover_image_path' => $instance->cover_image_path,
                'original_package_total' => (string) $instance->original_package_total,
                'configured_package_total' => (string) $instance->original_package_total,
                'components' => $originalComponents,
                'available_all_branches' => false,
                'branch_ids' => [$this->order->branch_id],
            ];
            $configuredSnapshot = [
                ...$originalSnapshot,
                'configured_package_total' => (string) $instance->configured_package_total,
                'components' => $configuredComponents,
            ];
            $this->packages[$key] = [
                'instance_id' => $instance->id,
                'template_id' => $instance->order_package_template_id,
                'original_snapshot' => $originalSnapshot,
                'configured_snapshot' => $configuredSnapshot,
                'snapshot_signature' => $this->signPackageSnapshot($originalSnapshot),
            ];
            $packageKeysByInstance[$instance->id] = $key;
        }

        $this->lines = [];
        foreach ($this->order->lines as $line) {
            $measurementState = app(OrderMeasurementSnapshot::class)->editorState($line->measurement);

            $this->lines[] = [
                'id' => $line->id,
                'inventory_item_id' => $line->inventory_item_id,
                'order_catalog_item_id' => $line->order_catalog_item_id,
                'order_package_instance_id' => $line->order_package_instance_id,
                'order_package_template_item_id' => $line->order_package_template_item_id,
                'package_key' => $packageKeysByInstance[$line->order_package_instance_id] ?? null,
                'package_unit_index' => $line->meta['package_unit_index'] ?? null,
                'requires_measurements' => (bool) ($line->meta['requires_measurements'] ?? false),
                'sku' => $line->sku,
                'assigned_tailor_id' => $line->assigned_tailor_id,
                'item_name' => $line->item_name,
                'qty' => (float) $line->qty,
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->line_total,
                'notes' => $line->notes ?? '',
                ...$measurementState,
            ];
        }
        $this->lines = app(OrderMeasurementService::class)->initializeLineTemplates($this->lines);

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
        $line = [
            'id' => null,
            'inventory_item_id' => null,
            'order_catalog_item_id' => null,
            'order_package_instance_id' => null,
            'order_package_template_item_id' => null,
            'package_key' => null,
            'package_unit_index' => null,
            'requires_measurements' => false,
            'sku' => null,
            'assigned_tailor_id' => null,
            'item_name' => '',
            'qty' => 1,
            'unit_price' => 0,
            'line_total' => 0,
            'notes' => '',
            ...app(OrderMeasurementSnapshot::class)->emptyEditorState(),
        ];
        $this->lines[] = app(OrderMeasurementService::class)->initializeLineTemplates([$line])[0];

        $this->syncOrderExpensesWithSelectedTailors();
    }

    public function toggleCatalogPicker(): void
    {
        $this->showCatalogPicker = ! $this->showCatalogPicker;
    }

    public function addInventoryLine(int $inventoryItemId): void
    {
        $branchId = $this->getEffectiveBranchIdForInventory();

        if (! $branchId) {
            $this->addError('branch_id', 'Select a branch before adding inventory items.');

            return;
        }

        $item = InventoryItem::withoutBranchScope()
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
            'order_catalog_item_id' => null,
            'order_package_instance_id' => null,
            'order_package_template_item_id' => null,
            'package_key' => null,
            'package_unit_index' => null,
            'requires_measurements' => false,
            'sku' => $item->sku,
            'assigned_tailor_id' => null,
            'item_name' => $item->name,
            'qty' => 1,
            'unit_price' => (float) ($item->default_sell_price ?? 0),
            'line_total' => (float) ($item->default_sell_price ?? 0),
            'notes' => '',
            ...app(OrderMeasurementSnapshot::class)->emptyEditorState(),
        ];
        $newLine = app(OrderMeasurementService::class)->initializeLineTemplates([$newLine])[0];

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

    public function setCatalogTab(string $tab): void
    {
        abort_unless(in_array($tab, ['packages', 'catalog', 'inventory'], true), 404);
        $this->catalogTab = $tab;
        $this->catalogSearch = '';
    }

    public function configureDirectCatalogItem(int $itemId): void
    {
        $branchId = $this->getEffectiveBranchIdForInventory();
        if (! $branchId) {
            $this->addError('branch_id', __('Select a branch before adding catalog items.'));

            return;
        }

        app(OrderCatalogCompositionService::class)->catalogItemForSelection($itemId, $branchId);
        $this->selectedCatalogItemId = $itemId;
        $this->directCatalogQuantity = '1';
        $this->showDirectCatalogConfigurator = true;
    }

    public function confirmDirectCatalogItem(): void
    {
        $this->validate(['directCatalogQuantity' => ['required', 'numeric', 'min:0.01']]);
        $branchId = $this->getEffectiveBranchIdForInventory();

        try {
            $item = app(OrderCatalogCompositionService::class)->catalogItemForSelection((int) $this->selectedCatalogItemId, (int) $branchId);
            $newLines = app(OrderCatalogCompositionService::class)->directCatalogLines($item, $this->directCatalogQuantity);
        } catch (\Throwable $exception) {
            $this->addError('directCatalogQuantity', $exception->getMessage());

            return;
        }

        $this->appendCompositionLines($newLines);
        $this->showDirectCatalogConfigurator = false;
        $this->showCatalogPicker = false;
    }

    public function configurePackage(int $templateId): void
    {
        $branchId = $this->getEffectiveBranchIdForInventory();
        if (! $branchId) {
            $this->addError('branch_id', __('Select a branch before adding packages.'));

            return;
        }

        $template = app(OrderCatalogCompositionService::class)->packageForSelection($templateId, $branchId);
        $snapshot = $template->snapshot();
        $snapshot['available_all_branches'] = $template->available_all_branches;
        $snapshot['branch_ids'] = $template->branches->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->configuringPackageKey = null;
        $this->packageConfigurator = $snapshot;
        $this->packageQuantities = collect($snapshot['components'])
            ->mapWithKeys(fn ($component) => [(int) $component['template_item_id'] => $component['default_quantity']])
            ->all();
        $this->showPackageConfigurator = true;
    }

    public function customizePackage(string $packageKey): void
    {
        abort_unless(isset($this->packages[$packageKey]), 404);
        $package = $this->packages[$packageKey];
        $this->configuringPackageKey = $packageKey;
        $this->packageConfigurator = $package['original_snapshot'];
        $this->packageQuantities = collect($package['configured_snapshot']['components'] ?? [])
            ->mapWithKeys(fn ($component) => [(int) $component['template_item_id'] => $component['configured_quantity']])
            ->all();
        $this->showPackageConfigurator = true;
    }

    public function confirmPackageConfiguration(): void
    {
        try {
            $configured = app(OrderPackagePricingService::class)->configureCapturedSnapshot(
                $this->packageConfigurator,
                $this->packageQuantities
            );
            app(OrderCatalogCompositionService::class)->packageLines($configured, 'configuration-preview');
        } catch (\Throwable $exception) {
            $this->addError('packageQuantities', $exception->getMessage());

            return;
        }

        $key = $this->configuringPackageKey ?: (string) Str::uuid();
        $existingInstanceId = $this->packages[$key]['instance_id'] ?? null;
        $this->packages[$key] = [
            'instance_id' => $existingInstanceId,
            'template_id' => $this->packageConfigurator['template_id'],
            'original_snapshot' => $this->packageConfigurator,
            'configured_snapshot' => $configured,
            'snapshot_signature' => $this->packages[$key]['snapshot_signature'] ?? $this->signPackageSnapshot($this->packageConfigurator),
        ];
        $this->reconcilePackageLines($key);
        $this->showPackageConfigurator = false;
        $this->showCatalogPicker = false;
        $this->calculateTotals();
    }

    public function removePackage(string $packageKey): void
    {
        abort_unless(isset($this->packages[$packageKey]), 404);
        unset($this->packages[$packageKey]);
        $this->lines = array_values(array_filter(
            $this->lines,
            fn ($line) => ($line['package_key'] ?? null) !== $packageKey
        ));
        $this->calculateTotals();
        $this->syncOrderExpensesWithSelectedTailors();
    }

    public function removeLine(int $index): void
    {
        if (filled($this->lines[$index]['package_key'] ?? null)) {
            $this->addError('lines', __('Package items must be changed or removed using the package controls.'));

            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->calculateTotals();
        $this->syncOrderExpensesWithSelectedTailors();
    }

    public function addMeasurement(int $lineIndex): void
    {
        $this->authorizeMeasurementInteraction();
        abort_unless(isset($this->lines[$lineIndex]) && ($this->lines[$lineIndex]['measurement_enabled'] ?? false), 404);

        $selection = (string) ($this->lines[$lineIndex]['measurement_field_selection'] ?? '');
        if ($selection === 'custom') {
            $this->addCustomMeasurement($lineIndex);

            return;
        }

        $fieldId = filter_var($selection, FILTER_VALIDATE_INT);
        if (! $fieldId) {
            $this->addError("lines.$lineIndex.measurement_field_selection", __('Select a measurement to add.'));

            return;
        }

        if (collect($this->lines[$lineIndex]['measurements'])->contains(
            fn (array $row): bool => (int) ($row['measurement_field_id'] ?? 0) === $fieldId
        )) {
            $this->addError("lines.$lineIndex.measurement_field_selection", __('That measurement is already included for this garment.'));

            return;
        }

        $field = MeasurementField::query()->active()->findOrFail($fieldId);
        $this->lines[$lineIndex]['measurements'][] = [
            'measurement_field_id' => $field->id,
            'code' => $field->code,
            'label' => $field->name,
            'value' => '',
            'unit' => $field->default_unit,
            'required' => false,
            'expected' => false,
            'is_custom' => false,
            'instructions' => $field->instructions,
            'default_unit' => $field->default_unit,
            'from_snapshot' => false,
            'value_source' => null,
        ];
        $this->lines[$lineIndex]['measurement_field_selection'] = '';
        $this->resetErrorBag("lines.$lineIndex.measurement_field_selection");
    }

    public function addCustomMeasurement(int $lineIndex): void
    {
        $this->authorizeMeasurementInteraction();
        abort_unless(isset($this->lines[$lineIndex]) && ($this->lines[$lineIndex]['measurement_enabled'] ?? false), 404);

        $this->lines[$lineIndex]['measurements'][] = [
            'measurement_field_id' => null,
            'code' => null,
            'label' => '',
            'value' => '',
            'unit' => 'cm',
            'required' => false,
            'expected' => false,
            'is_custom' => true,
            'instructions' => null,
            'default_unit' => 'cm',
            'from_snapshot' => false,
            'value_source' => null,
        ];
        $this->lines[$lineIndex]['measurement_field_selection'] = '';
    }

    public function removeMeasurement(int $lineIndex, int $measurementIndex): void
    {
        $this->authorizeMeasurementInteraction();
        abort_unless(isset($this->lines[$lineIndex]['measurements'][$measurementIndex]), 404);

        if ($this->lines[$lineIndex]['measurements'][$measurementIndex]['expected'] ?? false) {
            $this->lines[$lineIndex]['measurements'][$measurementIndex]['value'] = '';
            $this->lines[$lineIndex]['measurements'][$measurementIndex]['value_source'] = null;

            return;
        }

        unset($this->lines[$lineIndex]['measurements'][$measurementIndex]);
        $this->lines[$lineIndex]['measurements'] = array_values($this->lines[$lineIndex]['measurements']);
    }

    public function applySelectedMeasurementProfile(int $lineIndex): void
    {
        $this->authorizeMeasurementInteraction();
        abort_unless(isset($this->lines[$lineIndex]), 404);

        $profileId = filter_var($this->lines[$lineIndex]['measurement_profile_selection'] ?? null, FILTER_VALIDATE_INT);
        if (! $profileId) {
            $this->addError("lines.$lineIndex.measurement_profile_selection", __('Select a saved measurement revision.'));

            return;
        }

        if (app(OrderMeasurementService::class)->hasReplaceableValues($this->lines[$lineIndex])) {
            $this->lines[$lineIndex]['measurement_pending_profile_id'] = $profileId;

            return;
        }

        $this->applyMeasurementProfile($lineIndex, $profileId);
    }

    public function confirmApplyMeasurementProfile(int $lineIndex): void
    {
        $this->authorizeMeasurementInteraction();
        abort_unless(isset($this->lines[$lineIndex]), 404);

        $profileId = filter_var($this->lines[$lineIndex]['measurement_pending_profile_id'] ?? null, FILTER_VALIDATE_INT);
        abort_unless($profileId, 404);
        $this->applyMeasurementProfile($lineIndex, $profileId);
    }

    public function cancelApplyMeasurementProfile(int $lineIndex): void
    {
        $this->authorizeMeasurementInteraction();
        abort_unless(isset($this->lines[$lineIndex]), 404);
        $this->lines[$lineIndex]['measurement_pending_profile_id'] = null;
    }

    public function enterMeasurementsManually(int $lineIndex): void
    {
        $this->authorizeMeasurementInteraction();
        abort_unless(isset($this->lines[$lineIndex]), 404);
        $this->lines[$lineIndex] = app(OrderMeasurementService::class)->enterManually($this->lines[$lineIndex]);
    }

    private function applyMeasurementProfile(int $lineIndex, int $profileId): void
    {
        $customer = $this->selectedCustomer;
        if (! $customer) {
            $this->addError("lines.$lineIndex.measurement_profile_selection", __('Select an authorized customer before using saved measurements.'));

            return;
        }

        try {
            $this->lines[$lineIndex] = app(OrderMeasurementService::class)
                ->applyProfile($this->lines[$lineIndex], $customer, $profileId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->addError("lines.$lineIndex.measurement_profile_selection", __('That saved measurement revision is not available for the selected customer.'));
        }
    }

    public function openMeasurementModal(int $lineIndex): void
    {
        $this->authorizeMeasurementInteraction();
        abort_unless(isset($this->lines[$lineIndex]) && ($this->lines[$lineIndex]['measurement_enabled'] ?? false), 404);

        $this->measurementModalLineIndex = $lineIndex;
        $this->measurementDraft = $this->withInitialMeasurementUnits($this->lines[$lineIndex]);
        $this->showMeasurementModal = true;
        $this->resetErrorBag('measurementDraft');
    }

    public function cancelMeasurementModal(): void
    {
        $this->showMeasurementModal = false;
        $this->measurementModalLineIndex = null;
        $this->measurementDraft = [];
        $this->resetErrorBag('measurementDraft');
    }

    public function addDraftMeasurement(): void
    {
        $this->authorizeMeasurementInteraction();
        abort_unless($this->showMeasurementModal && $this->measurementModalLineIndex !== null, 404);

        $selection = (string) ($this->measurementDraft['measurement_field_selection'] ?? '');
        if ($selection === 'custom') {
            $this->measurementDraft['measurements'][] = [
                'measurement_field_id' => null,
                'code' => null,
                'label' => '',
                'value' => '',
                'unit' => 'cm',
                'initial_unit' => 'cm',
                'unit_change_confirmed' => false,
                'required' => false,
                'expected' => false,
                'is_custom' => true,
                'instructions' => null,
                'default_unit' => 'cm',
                'from_snapshot' => false,
                'value_source' => null,
            ];
            $this->measurementDraft['measurement_field_selection'] = '';

            return;
        }

        $fieldId = filter_var($selection, FILTER_VALIDATE_INT);
        if (! $fieldId) {
            $this->addError('measurementDraft.measurement_field_selection', __('Select a measurement to add.'));

            return;
        }
        if (collect($this->measurementDraft['measurements'] ?? [])->contains(
            fn (array $row): bool => (int) ($row['measurement_field_id'] ?? 0) === $fieldId
        )) {
            $this->addError('measurementDraft.measurement_field_selection', __('That measurement is already included for this garment.'));

            return;
        }

        $field = MeasurementField::query()->active()->findOrFail($fieldId);
        $this->measurementDraft['measurements'][] = [
            'measurement_field_id' => $field->id,
            'code' => $field->code,
            'label' => $field->name,
            'value' => '',
            'unit' => $field->default_unit,
            'initial_unit' => $field->default_unit,
            'unit_change_confirmed' => false,
            'required' => false,
            'expected' => false,
            'is_custom' => false,
            'instructions' => $field->instructions,
            'default_unit' => $field->default_unit,
            'from_snapshot' => false,
            'value_source' => null,
        ];
        $this->measurementDraft['measurement_field_selection'] = '';
        $this->resetErrorBag('measurementDraft.measurement_field_selection');
    }

    public function removeDraftMeasurement(int $measurementIndex): void
    {
        $this->authorizeMeasurementInteraction();
        abort_unless(isset($this->measurementDraft['measurements'][$measurementIndex]), 404);

        if ($this->measurementDraft['measurements'][$measurementIndex]['expected'] ?? false) {
            $this->measurementDraft['measurements'][$measurementIndex]['value'] = '';
            $this->measurementDraft['measurements'][$measurementIndex]['value_source'] = null;

            return;
        }

        unset($this->measurementDraft['measurements'][$measurementIndex]);
        $this->measurementDraft['measurements'] = array_values($this->measurementDraft['measurements']);
    }

    public function applySelectedDraftMeasurementProfile(): void
    {
        $this->authorizeMeasurementInteraction();
        $profileId = filter_var($this->measurementDraft['measurement_profile_selection'] ?? null, FILTER_VALIDATE_INT);
        if (! $profileId) {
            $this->addError('measurementDraft.measurement_profile_selection', __('Select a saved measurement revision.'));

            return;
        }

        if (app(OrderMeasurementService::class)->hasReplaceableValues($this->measurementDraft)) {
            $this->measurementDraft['measurement_pending_profile_id'] = $profileId;

            return;
        }

        $this->applyDraftMeasurementProfile($profileId);
    }

    public function confirmApplyDraftMeasurementProfile(): void
    {
        $this->authorizeMeasurementInteraction();
        $profileId = filter_var($this->measurementDraft['measurement_pending_profile_id'] ?? null, FILTER_VALIDATE_INT);
        abort_unless($profileId, 404);
        $this->applyDraftMeasurementProfile($profileId);
    }

    public function cancelApplyDraftMeasurementProfile(): void
    {
        $this->measurementDraft['measurement_pending_profile_id'] = null;
    }

    public function enterDraftMeasurementsManually(): void
    {
        $this->authorizeMeasurementInteraction();
        $this->measurementDraft = app(OrderMeasurementService::class)->enterManually($this->measurementDraft);
    }

    public function applyMeasurementModal(): void
    {
        $this->authorizeMeasurementInteraction();
        $lineIndex = $this->measurementModalLineIndex;
        abort_unless($lineIndex !== null && isset($this->lines[$lineIndex]), 404);

        foreach ($this->measurementDraft['measurements'] ?? [] as $index => $row) {
            $this->resetErrorBag("measurementDraft.measurements.$index.unit_change_confirmed");
            $initialUnit = (string) ($row['initial_unit'] ?? $row['unit'] ?? 'cm');
            if (($row['unit'] ?? $initialUnit) !== $initialUnit && ! ($row['unit_change_confirmed'] ?? false)) {
                $this->addError("measurementDraft.measurements.$index.unit_change_confirmed", __('Confirm that the entered value matches the selected unit. Values are not converted automatically.'));

                return;
            }
        }

        foreach ([
            'measurement_format',
            'measurements',
            'measurement_source_profile_id',
            'measurement_source_lineage',
            'measurement_source_revision',
            'measurement_source_name',
            'measurement_source_measured_at',
            'measurement_profile_selection',
            'measurement_pending_profile_id',
        ] as $key) {
            $this->lines[$lineIndex][$key] = $this->measurementDraft[$key] ?? null;
        }

        $this->cancelMeasurementModal();
    }

    private function applyDraftMeasurementProfile(int $profileId): void
    {
        $customer = $this->selectedCustomer;
        if (! $customer) {
            $this->addError('measurementDraft.measurement_profile_selection', __('Select an authorized customer before using saved measurements.'));

            return;
        }

        try {
            $this->measurementDraft = app(OrderMeasurementService::class)
                ->applyProfile($this->measurementDraft, $customer, $profileId);
            $this->measurementDraft = $this->withInitialMeasurementUnits($this->measurementDraft);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->addError('measurementDraft.measurement_profile_selection', __('That saved measurement revision is not available for the selected customer.'));
        }
    }

    /** @param array<string, mixed> $state */
    private function withInitialMeasurementUnits(array $state): array
    {
        $state['measurements'] = collect($state['measurements'] ?? [])->map(function (array $row): array {
            $row['initial_unit'] = $row['unit'] ?? $row['default_unit'] ?? 'cm';
            $row['unit_change_confirmed'] = false;

            return $row;
        })->values()->all();

        return $state;
    }

    private function authorizeMeasurementInteraction(): void
    {
        if ($this->isEdit && $this->order) {
            $this->authorize('update', $this->order);

            return;
        }

        $this->authorize('create', Order::class);
    }

    public function addOrderExpense(): void
    {
        $this->normalizeOrderExpenses();
        $this->order_expenses[] = $this->emptyOrderExpenseRow();
    }

    public function removeOrderExpense(int $index): void
    {
        if (count($this->order_expenses) > 1) {
            unset($this->order_expenses[$index]);
            $this->order_expenses = array_values($this->order_expenses);

            return;
        }

        $this->order_expenses = [];
    }

    protected function normalizeOrderExpenses(): void
    {
        if (empty($this->order_expenses)) {
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

    /** Keep expense rows independent from tailor assignments. */
    protected function syncOrderExpensesWithSelectedTailors(): void
    {
        $this->normalizeOrderExpenses();
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
        $candidateBranchId = $branchId > 0 ? $branchId : null;

        if ($candidateBranchId === $this->previousBranchId) {
            $this->mustSelectBranch = $this->showBranchSelector && ! $this->isEdit && $candidateBranchId === null;

            return;
        }

        if ($this->hasNewCustomerDraft()) {
            $this->branch_id = $this->previousBranchId;
            $this->addError('branch_id', __('Cancel the unsaved new customer details before changing the order branch.'));

            return;
        }

        if ($candidateBranchId && ! $this->contentsAreCompatibleWithBranch($candidateBranchId)) {
            $attemptedBranch = Branch::query()->find($candidateBranchId)?->name ?? __('the selected branch');
            $this->branch_id = $this->previousBranchId;
            $this->addError('branch_id', __('This order contains items unavailable at :branch. Remove those items before changing the order branch.', ['branch' => $attemptedBranch]));

            return;
        }

        $this->branch_id = $candidateBranchId;
        $this->previousBranchId = $candidateBranchId;
        $this->mustSelectBranch = $this->showBranchSelector && ! $this->isEdit && $this->branch_id === null;

        $selectedCustomerBranchId = $this->customer_id
            ? Customer::withoutBranchScope()->whereKey($this->customer_id)->value('branch_id')
            : null;
        if (! $candidateBranchId || ($selectedCustomerBranchId && (int) $selectedCustomerBranchId !== $candidateBranchId)) {
            $this->clearSelectedCustomer();
        } elseif (! $this->customer_id) {
            $this->customerSearch = '';
            $this->showCustomerDropdown = false;
        }

        $this->resetNewCustomerDraft();
        $this->resetBranchDependentPickerState();

        // Prevent stale cross-branch assignments after branch change.
        $this->assigned_tailor_id = null;
        $this->lines = array_values(array_map(function ($line) {
            $line['assigned_tailor_id'] = null;

            return $line;
        }, $this->lines));

        $this->syncOrderExpensesWithSelectedTailors();
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
        $this->normalizeMoneyInputs();
        $this->calculateTotals();
        $this->syncOrderExpensesWithSelectedTailors();
    }

    public function updatedDiscount(): void
    {
        $this->normalizeMoneyInputProperty('discount');
        $this->calculateTotals();
    }

    public function updatedDepositAmount(): void
    {
        $this->normalizeMoneyInputProperty('deposit_amount');
        $this->calculateTotals();
        $amount = $this->deposit_amount === null || $this->deposit_amount === '' ? 0 : (float) $this->deposit_amount;
        if ($amount > $this->total) {
            $this->deposit_amount = $this->total;
        }
    }

    protected function calculateTotals(): void
    {
        $subtotal = BigDecimal::zero();

        foreach ($this->lines as $index => $line) {
            try {
                $qty = BigDecimal::of(filled($line['qty'] ?? null) ? (string) $line['qty'] : '0');
                $unitPrice = BigDecimal::of(filled($line['unit_price'] ?? null) ? (string) $line['unit_price'] : '0');
                $lineTotal = $qty->multipliedBy($unitPrice)->toScale(2, RoundingMode::HALF_UP);
            } catch (\Throwable) {
                $lineTotal = BigDecimal::zero()->toScale(2);
            }

            $this->lines[$index]['line_total'] = (float) (string) $lineTotal;
            $subtotal = $subtotal->plus($lineTotal);
        }

        try {
            $discount = BigDecimal::of(filled($this->discount) ? (string) $this->discount : '0');
        } catch (\Throwable) {
            $discount = BigDecimal::zero();
        }

        $this->subtotal = (float) (string) $subtotal->toScale(2, RoundingMode::HALF_UP);
        $this->total = (float) (string) $subtotal->minus($discount)->toScale(2, RoundingMode::HALF_UP);
    }

    /** @param array<int, array<string, mixed>> $newLines */
    protected function appendCompositionLines(array $newLines): void
    {
        $newLines = app(OrderMeasurementService::class)->initializeLineTemplates($newLines);
        $hasOnlyBlankLine = count($this->lines) === 1
            && empty($this->lines[0]['id'])
            && empty($this->lines[0]['item_name'])
            && empty($this->lines[0]['inventory_item_id'])
            && empty($this->lines[0]['order_catalog_item_id']);

        $this->lines = $hasOnlyBlankLine ? $newLines : [...$this->lines, ...$newLines];
        $this->calculateTotals();
        $this->syncOrderExpensesWithSelectedTailors();
    }

    protected function reconcilePackageLines(string $packageKey): void
    {
        $desiredLines = app(OrderCatalogCompositionService::class)->packageLines(
            $this->packages[$packageKey]['configured_snapshot'],
            $packageKey
        );
        $desiredLines = app(OrderMeasurementService::class)->initializeLineTemplates($desiredLines);
        $existingLines = collect($this->lines)
            ->filter(fn ($line) => ($line['package_key'] ?? null) === $packageKey)
            ->values();
        $reconciled = [];

        foreach ($desiredLines as $desired) {
            $matchIndex = $existingLines->search(fn ($existing) => (int) ($existing['order_package_template_item_id'] ?? 0) === (int) $desired['order_package_template_item_id']
                && (int) ($existing['package_unit_index'] ?? 0) === (int) ($desired['package_unit_index'] ?? 0)
            );

            if ($matchIndex !== false) {
                $existing = $existingLines->get($matchIndex);
                $desired['id'] = $existing['id'] ?? null;
                $desired['assigned_tailor_id'] = $existing['assigned_tailor_id'] ?? null;
                $desired['notes'] = $existing['notes'] ?? '';
                foreach (array_keys(app(OrderMeasurementSnapshot::class)->emptyEditorState()) as $measurementStateKey) {
                    $desired[$measurementStateKey] = $existing[$measurementStateKey]
                        ?? $desired[$measurementStateKey]
                        ?? app(OrderMeasurementSnapshot::class)->emptyEditorState()[$measurementStateKey];
                }
                $desired['order_package_instance_id'] = $existing['order_package_instance_id'] ?? null;
                $existingLines->forget($matchIndex);
            }

            $reconciled[] = $desired;
        }
        $reconciled = app(OrderMeasurementService::class)->initializeLineTemplates($reconciled);

        $firstIndex = collect($this->lines)->search(fn ($line) => ($line['package_key'] ?? null) === $packageKey);
        $ordinaryLines = array_values(array_filter($this->lines, fn ($line) => ($line['package_key'] ?? null) !== $packageKey));
        if ($firstIndex === false
            && count($ordinaryLines) === 1
            && empty($ordinaryLines[0]['id'])
            && empty($ordinaryLines[0]['item_name'])
            && empty($ordinaryLines[0]['inventory_item_id'])
            && empty($ordinaryLines[0]['order_catalog_item_id'])) {
            $ordinaryLines = [];
        }
        $insertAt = $firstIndex === false ? count($ordinaryLines) : min((int) $firstIndex, count($ordinaryLines));
        array_splice($ordinaryLines, $insertAt, 0, $reconciled);
        $this->lines = $ordinaryLines;
    }

    protected function contentsAreCompatibleWithBranch(int $branchId): bool
    {
        $inventoryIds = collect($this->lines)->pluck('inventory_item_id')->filter()->map(fn ($id) => (int) $id)->unique();
        if ($inventoryIds->isNotEmpty()) {
            $validInventoryCount = InventoryItem::withoutBranchScope()
                ->whereKey($inventoryIds)
                ->where('branch_id', $branchId)
                ->count();
            if ($validInventoryCount !== $inventoryIds->count()) {
                return false;
            }
        }

        $catalogIds = collect($this->lines)
            ->filter(fn ($line) => blank($line['package_key'] ?? null))
            ->pluck('order_catalog_item_id')->filter()->map(fn ($id) => (int) $id)->unique();
        if ($catalogIds->isNotEmpty() && OrderCatalogItem::query()->whereKey($catalogIds)->availableForBranch($branchId)->count() !== $catalogIds->count()) {
            return false;
        }

        foreach ($this->packages as $package) {
            $snapshot = $package['original_snapshot'];
            if (! ($snapshot['available_all_branches'] ?? false)
                && ! in_array($branchId, array_map('intval', $snapshot['branch_ids'] ?? []), true)) {
                return false;
            }

            foreach ($snapshot['components'] ?? [] as $component) {
                if (($component['source_type'] ?? null) === 'inventory_item') {
                    $item = InventoryItem::withoutBranchScope()->find($component['source_id']);
                    if (! $item || (int) $item->branch_id !== $branchId) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    protected function normalizePackageCompositionForSave(int $branchId): bool
    {
        try {
            foreach (array_keys($this->packages) as $packageKey) {
                $package = $this->packages[$packageKey];
                $original = $package['original_snapshot'] ?? [];
                if (! hash_equals((string) ($package['snapshot_signature'] ?? ''), $this->signPackageSnapshot($original))) {
                    throw new \DomainException('Package snapshot validation failed. Remove the package and add it again.');
                }

                $quantities = collect($package['configured_snapshot']['components'] ?? [])
                    ->mapWithKeys(fn ($component) => [(int) $component['template_item_id'] => $component['configured_quantity']])
                    ->all();
                $this->packages[$packageKey]['configured_snapshot'] = app(OrderPackagePricingService::class)
                    ->configureCapturedSnapshot($original, $quantities);
                $this->reconcilePackageLines($packageKey);
            }

            if (! $this->contentsAreCompatibleWithBranch($branchId)) {
                throw new \DomainException('One or more catalog or package items are not available for this order branch.');
            }
        } catch (\Throwable $exception) {
            $this->addError('lines', $exception->getMessage());

            return false;
        }

        return true;
    }

    protected function validateDirectCatalogLines(int $branchId): bool
    {
        foreach ($this->lines as $index => $line) {
            $catalogItemId = (int) ($line['order_catalog_item_id'] ?? 0);
            if ($catalogItemId <= 0 || filled($line['package_key'] ?? null) || filled($line['id'] ?? null)) {
                continue;
            }

            if (! OrderCatalogItem::query()->active()->availableForBranch($branchId)->whereKey($catalogItemId)->exists()) {
                $this->addError("lines.{$index}.order_catalog_item_id", __('Selected catalog item is inactive or unavailable for this order branch.'));

                return false;
            }
        }

        foreach ($this->lines as $index => $line) {
            $packageKey = $line['package_key'] ?? null;
            if ($packageKey && ! isset($this->packages[$packageKey])) {
                $this->addError("lines.{$index}.package_key", __('Package line provenance is invalid.'));

                return false;
            }
        }

        return true;
    }

    protected function signPackageSnapshot(array $snapshot): string
    {
        return hash_hmac('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function updatedCustomerSearch(): void
    {
        $this->showCustomerDropdown = strlen($this->customerSearch) >= 2;
        if (! $this->isEdit && $this->customer_id) {
            $this->clearUnsavedProfileDerivedMeasurements();
        }
        $this->customer_id = null;
    }

    public function selectCustomer(int $customerId): void
    {
        $branchId = $this->getEffectiveBranchIdForCustomerSearch();
        $customer = Customer::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('id', $customerId)
            ->first();
        if ($customer) {
            if (! $this->isEdit && $this->customer_id && $this->customer_id !== $customer->id) {
                $this->clearUnsavedProfileDerivedMeasurements();
            }
            $this->customer_id = $customer->id;
            $this->customerSearch = $customer->name;
        }
        $this->showCustomerDropdown = false;
    }

    public function clearSelectedCustomer(): void
    {
        if (! $this->isEdit && $this->customer_id) {
            $this->clearUnsavedProfileDerivedMeasurements();
        }
        $this->customer_id = null;
        $this->customerSearch = '';
        $this->showCustomerDropdown = false;
    }

    protected function clearUnsavedProfileDerivedMeasurements(): void
    {
        $service = app(OrderMeasurementService::class);
        $this->lines = collect($this->lines)
            ->map(fn (array $line): array => $service->clearUnsavedProfileValues($line))
            ->values()
            ->all();
    }

    public function openNewCustomerModal(): void
    {
        $this->authorize('customers.create');
        $branchId = $this->getEffectiveBranchIdForCustomerSearch();
        if (! $branchId || ! Branch::query()->active()->whereKey($branchId)->exists()) {
            $this->addError('branch_id', __('Select a valid order branch before creating a customer.'));

            return;
        }

        $this->resetNewCustomerDraft();
        $this->showNewCustomerModal = true;
    }

    public function cancelNewCustomerModal(): void
    {
        $this->resetNewCustomerDraft();
    }

    public function createNewCustomer(CustomerCreator $creator): void
    {
        $this->authorize('customers.create');
        $branchId = $this->getEffectiveBranchIdForCustomerSearch();
        if (! $branchId) {
            $this->addError('branch_id', __('Select a valid order branch before creating a customer.'));

            return;
        }

        $customerRules = $creator->rules($branchId);
        $validated = $this->validate([
            'newCustomerName' => $customerRules['name'],
            'newCustomerPhone' => $customerRules['phone'],
            'newCustomerEmail' => $customerRules['email'],
            'newCustomerAddress' => $customerRules['address'],
        ]);

        $customer = $creator->create(auth()->user(), $branchId, [
            'name' => $validated['newCustomerName'],
            'phone' => $validated['newCustomerPhone'],
            'email' => $validated['newCustomerEmail'],
            'address' => $validated['newCustomerAddress'],
        ]);

        $this->customer_id = $customer->id;
        $this->customerSearch = $customer->name;
        $this->showCustomerDropdown = false;
        $this->resetValidation('customer_id');
        $this->resetNewCustomerDraft();
    }

    protected function hasNewCustomerDraft(): bool
    {
        return $this->showNewCustomerModal && collect([
            $this->newCustomerName,
            $this->newCustomerPhone,
            $this->newCustomerEmail,
            $this->newCustomerAddress,
        ])->contains(fn ($value) => trim((string) $value) !== '');
    }

    protected function resetNewCustomerDraft(): void
    {
        $this->showNewCustomerModal = false;
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerEmail = '';
        $this->newCustomerAddress = '';
        $this->resetValidation([
            'newCustomerName',
            'newCustomerPhone',
            'newCustomerEmail',
            'newCustomerAddress',
        ]);
    }

    protected function resetBranchDependentPickerState(): void
    {
        $this->showCustomerDropdown = false;
        $this->catalogSearch = '';
        $this->inventorySearch = '';
        $this->showCatalogPicker = false;
        $this->showDirectCatalogConfigurator = false;
        $this->selectedCatalogItemId = null;
        $this->directCatalogQuantity = '1';
        $this->showPackageConfigurator = false;
        $this->configuringPackageKey = null;
        $this->packageConfigurator = [];
        $this->packageQuantities = [];
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

        return Customer::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('id', $this->customer_id)
            ->first();
    }

    public function getTitle(): string
    {
        return $this->isEdit ? "Edit Order {$this->order->order_no}" : 'Create Order';
    }

    public function saveOrderOnly(): void
    {
        $this->saveCustomerMeasurementRevision = false;
        $this->showCustomerMeasurementSavebackModal = false;
        $this->save(skipCustomerMeasurementPrompt: true);
    }

    public function saveOrderWithCustomerMeasurements(): void
    {
        $this->saveCustomerMeasurementRevision = true;
        $this->save(skipCustomerMeasurementPrompt: true);
    }

    public function save(bool $skipCustomerMeasurementPrompt = false): void
    {
        $this->normalizeMoneyInputs();
        $user = auth()->user();
        $this->normalizeLineTailorAssignments();
        $this->syncOrderExpensesWithSelectedTailors();

        // Build validation rules
        $orderDateRules = ['required', 'date'];
        $dueDateRules = ['nullable', 'date'];

        $keepsOriginalOrderDate = $this->isEdit
            && $this->order?->order_date?->toDateString() === $this->order_date;

        if (! $this->allowsOrderDatesFlexibility()) {
            if (! $keepsOriginalOrderDate) {
                $orderDateRules[] = 'after_or_equal:today';
            }

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
            'lines.*.order_catalog_item_id' => ['nullable', 'integer', 'exists:order_catalog_items,id'],
            'lines.*.order_package_template_item_id' => ['nullable', 'integer', 'exists:order_package_template_items,id'],
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

        if (! $this->normalizePackageCompositionForSave($effectiveBranchId)
            || ! $this->validateDirectCatalogLines($effectiveBranchId)) {
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

        if ($this->customer_id && ! Customer::withoutBranchScope()
            ->whereKey($this->customer_id)
            ->where('branch_id', $effectiveBranchId)
            ->exists()) {
            $this->addError('customer_id', __('Select a customer that belongs to the order branch.'));

            return;
        }

        // Customers are persisted and selected before an order can be saved.
        if (! $this->customer_id) {
            $this->addError('customer_id', 'Please select or create a customer.');

            return;
        }

        // At least one line with item_name
        $hasValidLine = collect($this->lines)->some(fn ($line) => ! empty($line['item_name']));
        if (! $hasValidLine) {
            $this->addError('lines', 'At least one order line with an item name is required.');

            return;
        }

        $this->calculateTotals();

        if ($this->isEdit) {
            $paidAmount = (float) $this->order->payments()->sum('amount');
            if ($this->total < $paidAmount) {
                $this->addError('total', __('The order total cannot be reduced below :amount because that amount has already been paid.', [
                    'amount' => money_currency($paidAmount, config('app.currency', 'TZS')),
                ]));

                return;
            }
        }

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
            $preparedMeasurements = app(OrderMeasurementService::class)
                ->prepareSnapshots($this->lines, $this->selectedCustomer);

            $resolvedCustomerMeasurements = [];
            $savebackProposal = null;
            if (auth()->user()?->can('users.manage') && $this->selectedCustomer) {
                $savebackProposal = app(OrderMeasurementSavebackService::class)
                    ->proposal($this->selectedCustomer, $this->lines, $this->isEdit);
            }

            if (! $skipCustomerMeasurementPrompt && $savebackProposal) {
                $this->customerMeasurementSaveback = $savebackProposal;
                $this->customerMeasurementConflictChoices = [];
                $this->saveCustomerMeasurementRevision = false;
                $this->showCustomerMeasurementSavebackModal = true;

                return;
            }

            if ($this->saveCustomerMeasurementRevision && $savebackProposal) {
                CustomerAccess::authorizeManage($this->selectedCustomer);
                $resolvedCustomerMeasurements = app(OrderMeasurementSavebackService::class)
                    ->resolvedChanges($savebackProposal, $this->customerMeasurementConflictChoices);
            }

            DB::transaction(function () use ($effectiveBranchId, $preparedMeasurements, $resolvedCustomerMeasurements) {
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

                $packageInstanceIdsByKey = [];
                foreach ($this->packages as $packageKey => $packageState) {
                    $original = $packageState['original_snapshot'];
                    $configured = $packageState['configured_snapshot'];
                    $instance = filled($packageState['instance_id'] ?? null)
                        ? $order->packageInstances()->findOrFail((int) $packageState['instance_id'])
                        : new OrderPackageInstance(['order_id' => $order->id]);

                    if ($instance->exists) {
                        $instance->update([
                            'configured_package_total' => $configured['configured_package_total'],
                            'component_snapshot' => $configured['components'],
                            'configured_component_snapshot' => $configured['components'],
                            'configured_by' => auth()->id(),
                        ]);
                    } else {
                        $instance->fill([
                            'order_id' => $order->id,
                            'order_package_template_id' => $packageState['template_id'],
                            'source_template_revision' => $original['revision'],
                            'package_name' => $original['name'],
                            'package_description' => $original['description'],
                            'cover_image_path' => $original['cover_image_path'],
                            'original_package_total' => $original['original_package_total'],
                            'configured_package_total' => $configured['configured_package_total'],
                            'component_snapshot' => $configured['components'],
                            'original_component_snapshot' => $original['components'],
                            'configured_component_snapshot' => $configured['components'],
                            'configured_by' => auth()->id(),
                        ])->save();
                    }

                    $packageInstanceIdsByKey[$packageKey] = $instance->id;
                }

                // Handle lines
                $existingLineIds = [];

                foreach ($this->lines as $lineIndex => $lineData) {
                    if (empty($lineData['item_name'])) {
                        continue;
                    }

                    $lineAttributes = [
                        'order_id' => $order->id,
                        'inventory_item_id' => ($lineData['inventory_item_id'] ?? null) ?: null,
                        'order_catalog_item_id' => ($lineData['order_catalog_item_id'] ?? null) ?: null,
                        'order_package_instance_id' => filled($lineData['package_key'] ?? null)
                            ? ($packageInstanceIdsByKey[$lineData['package_key']] ?? null)
                            : null,
                        'order_package_template_item_id' => ($lineData['order_package_template_item_id'] ?? null) ?: null,
                        'sku' => $lineData['sku'] ?? null,
                        'assigned_tailor_id' => $lineData['assigned_tailor_id'] ?: null,
                        'item_name' => $lineData['item_name'],
                        'qty' => $lineData['qty'],
                        'unit_price' => $lineData['unit_price'],
                        'line_total' => $lineData['line_total'],
                        'notes' => $lineData['notes'] ?? null,
                        'meta' => [
                            ...((array) ($lineData['meta'] ?? [])),
                            'package_unit_index' => $lineData['package_unit_index'] ?? null,
                            'requires_measurements' => (bool) ($lineData['requires_measurements'] ?? false),
                        ],
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

                    app(OrderMeasurementService::class)
                        ->persistPrepared($line, $preparedMeasurements[$lineIndex] ?? ['action' => 'delete']);
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

                    $order->packageInstances()
                        ->whereNotIn('id', array_values($packageInstanceIdsByKey))
                        ->delete();
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
                                'tailor_id' => filled($expenseData['tailor_id'] ?? null) ? (int) $expenseData['tailor_id'] : null,
                                'amount' => (float) $amount,
                                'notes' => $notes ?: null,
                            ]);
                            $existingExpenseIds[] = $existingExpense->id;

                            continue;
                        }
                    }

                    $createdExpense = OrderExpense::create([
                        'order_id' => $order->id,
                        'tailor_id' => filled($expenseData['tailor_id'] ?? null) ? (int) $expenseData['tailor_id'] : null,
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

                $order->refresh();
                $order->update(['payment_status' => $order->computed_payment_status]);

                if (! $this->isEdit) {
                    event(new OrderCreated(
                        $order->fresh(['customer', 'lines']),
                        auth()->user(),
                        $depositPayment
                    ));
                }

                // Keep invoice aligned with current order details and lines.
                Invoice::syncFromOrder($order->fresh(['lines']), auth()->id());

                if ($this->saveCustomerMeasurementRevision && $resolvedCustomerMeasurements !== []) {
                    app(CustomerMeasurementProfileService::class)->createRevision(
                        customer: $this->selectedCustomer,
                        changes: $resolvedCustomerMeasurements,
                        removedFieldIds: [],
                        measuredAt: now(),
                        recordedBy: auth()->user(),
                        notes: __('Saved from order :order', ['order' => $order->order_no]),
                    );
                }

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

        $items = InventoryItem::withoutBranchScope()
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

        $item = InventoryItem::withoutBranchScope()
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

            $item = InventoryItem::withoutBranchScope()->find($transaction->inventory_item_id);
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
            $customers = Customer::withoutBranchScope()
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
        $catalogItems = collect();
        $catalogPackages = collect();
        $inventoryBranchId = $this->getEffectiveBranchIdForInventory();
        if ($this->showCatalogPicker && $inventoryBranchId && $this->catalogTab === 'inventory') {
            $inventoryItems = InventoryItem::withoutBranchScope()
                ->with('stock')
                ->where('branch_id', $inventoryBranchId)
                ->where('is_active', true)
                ->when(trim($this->catalogSearch) !== '', function ($query) {
                    $search = trim($this->catalogSearch);
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'branch_id', 'sku', 'name', 'default_sell_price', 'unit', 'featured_image_path']);
        }

        if ($this->showCatalogPicker && $inventoryBranchId && $this->catalogTab === 'catalog') {
            $catalogItems = OrderCatalogItem::query()
                ->active()
                ->availableForBranch($inventoryBranchId)
                ->when(trim($this->catalogSearch) !== '', function ($query) {
                    $search = trim($this->catalogSearch);
                    $query->where(fn ($searchQuery) => $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
                })
                ->orderBy('name')
                ->limit(12)
                ->get();
        }

        if ($this->showCatalogPicker && $inventoryBranchId && $this->catalogTab === 'packages') {
            $catalogPackages = OrderPackageTemplate::query()
                ->active()
                ->availableForBranch($inventoryBranchId)
                ->with(['items.catalogItem', 'items.inventoryItem'])
                ->when(trim($this->catalogSearch) !== '', function ($query) {
                    $search = trim($this->catalogSearch);
                    $query->where(fn ($searchQuery) => $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
                })
                ->orderBy('name')
                ->limit(12)
                ->get();

            $pricing = app(OrderPackagePricingService::class);
            $catalogPackages->each(fn ($package) => $package->setAttribute('pricing_summary', $pricing->summary($package)));
        }

        $selectedCatalogItem = $this->selectedCatalogItemId
            ? OrderCatalogItem::query()->find($this->selectedCatalogItemId)
            : null;
        $packageConfigurationPreview = null;
        if ($this->showPackageConfigurator && $this->packageConfigurator !== []) {
            try {
                $packageConfigurationPreview = app(OrderPackagePricingService::class)
                    ->configureCapturedSnapshot($this->packageConfigurator, $this->packageQuantities);
            } catch (\Throwable) {
                $packageConfigurationPreview = null;
            }
        }

        // Get branches for global admin selector
        $branches = $this->showBranchSelector
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();
        $effectiveBranchName = $inventoryBranchId
            ? Branch::query()->whereKey($inventoryBranchId)->value('name')
            : null;
        $selectedCustomer = $this->selectedCustomer;
        $measurementService = app(OrderMeasurementService::class);
        $measurementProfiles = $selectedCustomer
            ? $measurementService->boundedProfileOptions($selectedCustomer)
            : collect();
        $measurementFieldOptionsByLine = $measurementService->activeFieldOptionsByLine($this->lines);
        $measurementDraftFieldOptions = $this->showMeasurementModal
            ? ($measurementService->activeFieldOptionsByLine([$this->measurementDraft])[0] ?? collect())
            : collect();
        $measurementModalPackageName = null;
        if ($this->measurementModalLineIndex !== null) {
            $packageKey = $this->lines[$this->measurementModalLineIndex]['package_key'] ?? null;
            $measurementModalPackageName = $packageKey
                ? ($this->packages[$packageKey]['configured_snapshot']['name'] ?? null)
                : null;
        }

        return view('livewire.orders.form', [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'tailors' => $tailors,
            'priorities' => $priorities,
            'branches' => $branches,
            'paymentMethods' => $paymentMethods,
            'inventoryItems' => $inventoryItems,
            'catalogItems' => $catalogItems,
            'catalogPackages' => $catalogPackages,
            'selectedCatalogItem' => $selectedCatalogItem,
            'packageConfigurationPreview' => $packageConfigurationPreview,
            'allowOrderDatesFlexibility' => $this->allowsOrderDatesFlexibility(),
            'effectiveBranchName' => $effectiveBranchName,
            'measurementProfiles' => $measurementProfiles,
            'measurementFieldOptionsByLine' => $measurementFieldOptionsByLine,
            'measurementDraftFieldOptions' => $measurementDraftFieldOptions,
            'measurementModalPackageName' => $measurementModalPackageName,
        ])->title($this->getTitle());
    }
}

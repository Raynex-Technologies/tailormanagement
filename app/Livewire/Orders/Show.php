<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Enums\StorefrontFulfillmentStatus;
use App\Events\OrderDueDateChanged;
use App\Events\OrderStatusChanged;
use App\Models\BusinessSetting;
use App\Models\CustomOrderProgressUpdate;
use App\Models\DeliveryNote;
use App\Models\Order;
use App\Models\OrderStockRequest;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\CustomOrderProgressUpdatedNotification;
use App\Notifications\StorefrontOrderStatusUpdatedNotification;
use App\Notifications\StorefrontShipmentUpdatedNotification;
use App\Services\Orders\OrderDeletionService;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\OrderSmsTemplates;
use App\Support\DocNumber;
use App\Support\Livewire\NormalizesMoneyInputs;
use App\Support\Orders\OrderPackagePresenter;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    use NormalizesMoneyInputs;

    public Order $order;

    // Role-based visibility flags
    public bool $canViewFinancials = false;

    public bool $canViewMaterials = false;

    public bool $canManageMaterials = false;

    public bool $canViewPayments = false;

    public bool $canRecordPayments = false;

    public bool $canManageStorefrontOperations = false;

    /**
     * Listen for payment-recorded event to refresh order data.
     */
    #[On('payment-recorded')]
    public function refreshOrderData(): void
    {
        $this->order->refresh();
        $this->initializePermissions();
        $this->loadOrderRelations();
        $this->initializeStorefrontForms();
    }

    /**
     * Listen for stock-request-updated event to refresh materials data.
     */
    #[On('stock-request-updated')]
    public function refreshMaterialsData(): void
    {
        $this->order->refresh();
        $this->initializePermissions();
        $this->loadOrderRelations();
        $this->initializeStorefrontForms();
    }

    // Modals
    public bool $showStatusModal = false;

    public bool $showAssignTailorModal = false;

    public bool $showDeliveryNoteModal = false;

    public bool $showDueDateModal = false;

    // Form data
    public string $newStatus = '';

    public ?int $selectedTailorId = null;

    public string $receivedByName = '';

    public string $receivedByPhone = '';

    public ?string $updatedDueDate = null;

    public string $newFulfillmentStatus = '';

    public string $fulfillmentNote = '';

    public string $shipmentStatus = 'pending';

    public string $shipmentCarrierName = '';

    public string $shipmentTrackingNumber = '';

    public string $shipmentTrackingUrl = '';

    public string $shipmentNotes = '';

    public ?string $shipmentShippedAt = null;

    public ?string $shipmentDeliveredAt = null;

    public string $customStageKey = '';

    public string $customStageLabel = '';

    public string $customProgressNote = '';

    public bool $customProgressVisible = true;

    public string|float|null $customRequestedPaymentAmount = null;

    public string $customRequestedPaymentNote = '';

    public function mount(Order $order): void
    {
        $this->authorize('view', $order);
        $this->order = $order;
        $this->initializePermissions();
        $this->loadOrderRelations();
        $this->initializeStorefrontForms();
    }

    /**
     * Load order relationships based on user permissions.
     */
    protected function loadOrderRelations(): void
    {
        $relations = [
            'customer',
            'assignedTailor',
            'creator',
            'lines.measurement',
            'lines.assignedTailor',
            'packageInstances',
            'deliveryNote.deliveredBy',
            'branch',
            'statusHistory.actor',
            'customProgressUpdates.actor',
            'shipments',
            'currentShipment',
            'customer.user',
        ];

        if ($this->canViewFinancials) {
            $relations[] = 'invoice';
        }

        // Payment history is permission-gated and eager loaded to avoid per-row queries.
        if ($this->canViewPayments) {
            $relations[] = 'payments.paymentMethod';
            $relations[] = 'payments.receiver';
        }

        $this->order->load($relations);

        if ($this->canViewFinancials && ! $this->canViewPayments) {
            $this->order->loadSum('payments', 'amount');
        }
    }

    /**
     * Initialize permission flags for the view.
     */
    protected function initializePermissions(): void
    {
        $user = auth()->user();
        $orderIsCancelled = $this->order->status === OrderStatus::Cancelled;

        $this->canViewFinancials = $user->can('viewFinancials', $this->order);
        $this->canViewMaterials = $user->can('viewMaterials', $this->order);
        $this->canManageMaterials = $user->can('manageMaterials', $this->order);
        $this->canViewPayments = $user->can('viewPayments', $this->order);
        $this->canRecordPayments = ! $orderIsCancelled && $user->can('recordPayments', $this->order);
        $this->canManageStorefrontOperations = $user->can('storefront.orders.manage');
    }

    protected function initializeStorefrontForms(): void
    {
        $this->newFulfillmentStatus = $this->order->fulfillment_status?->value
            ?: StorefrontFulfillmentStatus::Pending->value;

        $shipment = $this->order->currentShipment;

        $this->shipmentStatus = (string) ($shipment?->status ?: 'pending');
        $this->shipmentCarrierName = (string) ($shipment?->carrier_name ?: '');
        $this->shipmentTrackingNumber = (string) ($shipment?->tracking_number ?: '');
        $this->shipmentTrackingUrl = (string) ($shipment?->tracking_url ?: '');
        $this->shipmentNotes = (string) ($shipment?->notes ?: '');
        $this->shipmentShippedAt = $shipment?->shipped_at?->format('Y-m-d\TH:i');
        $this->shipmentDeliveredAt = $shipment?->delivered_at?->format('Y-m-d\TH:i');

        $stageOptions = collect(config('storefront.custom_order_stages', []));
        $defaultStage = (string) ($stageOptions->first()['key'] ?? 'order_received');

        if ($this->customStageKey === '') {
            $this->customStageKey = $defaultStage;
        }
    }

    public function getTitle(): string
    {
        return "Order {$this->order->order_no}";
    }

    public function openStatusModal(): void
    {
        $this->authorize('changeStatus', $this->order);
        $this->newStatus = '';
        $this->showStatusModal = true;
    }

    public function changeStatus(): void
    {
        $this->authorize('changeStatus', $this->order);

        if (empty($this->newStatus)) {
            $this->addError('newStatus', 'Please select a status.');

            return;
        }

        $newStatusEnum = OrderStatus::from($this->newStatus);
        $oldStatus = $this->order->status;

        if ($newStatusEnum === OrderStatus::Delivered && $this->order->hasOutstandingBalance()) {
            $this->addError('newStatus', 'Order cannot be marked as delivered until balance is fully paid.');

            return;
        }

        if (! $this->order->canTransitionTo($newStatusEnum)) {
            $this->addError('newStatus', 'Invalid status transition.');

            return;
        }

        $this->order->update(['status' => $newStatusEnum]);
        $this->order->refresh();
        $this->showStatusModal = false;

        // Fire status changed event for SMS and notifications
        event(new OrderStatusChanged($this->order, $oldStatus, $newStatusEnum, auth()->user()));

        session()->flash('success', "Order status changed to {$newStatusEnum->label()}.");
    }

    public function markCompleted(): void
    {
        $this->authorize('markCompleted', $this->order);

        if (! $this->order->canTransitionTo(OrderStatus::Completed)) {
            session()->flash('error', 'Cannot mark this order as completed.');

            return;
        }

        $oldStatus = $this->order->status;
        $this->order->update(['status' => OrderStatus::Completed]);
        $this->order->refresh();

        // Fire status changed event for SMS and notifications
        event(new OrderStatusChanged($this->order, $oldStatus, OrderStatus::Completed, auth()->user()));

        session()->flash('success', 'Order marked as completed.');
    }

    public function cancelOrder(): void
    {
        $this->authorize('changeStatus', $this->order);

        if (in_array($this->order->status, [OrderStatus::Completed, OrderStatus::Cancelled])) {
            session()->flash('error', 'Cannot cancel this order.');

            return;
        }

        $oldStatus = $this->order->status;
        $this->order->update(['status' => OrderStatus::Cancelled]);
        $this->order->refresh();

        // Fire status changed event (cancelled doesn't trigger SMS per requirements)
        event(new OrderStatusChanged($this->order, $oldStatus, OrderStatus::Cancelled, auth()->user()));

        session()->flash('success', 'Order has been cancelled.');
    }

    public function deleteOrder(): void
    {
        $this->authorize('delete', $this->order);

        try {
            app(OrderDeletionService::class)->delete($this->order, auth()->user());
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Failed to delete order.');

            return;
        }

        session()->flash('success', 'Order deleted successfully.');
        $this->redirect(route('orders.index'), navigate: true);
    }

    public function openAssignTailorModal(): void
    {
        $this->authorize('assignTailor', $this->order);
        $this->selectedTailorId = $this->order->assigned_tailor_id;
        $this->showAssignTailorModal = true;
    }

    public function assignTailor(): void
    {
        $this->authorize('assignTailor', $this->order);

        if ($this->selectedTailorId) {
            $isValidTailor = User::query()
                ->whereKey($this->selectedTailorId)
                ->where('branch_id', $this->order->branch_id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'tailor'))
                ->exists();

            if (! $isValidTailor) {
                $this->addError('selectedTailorId', 'Selected tailor must belong to this order branch.');

                return;
            }
        }

        $this->order->update(['assigned_tailor_id' => $this->selectedTailorId ?: null]);
        $this->order->refresh();
        $this->showAssignTailorModal = false;

        $tailorName = $this->order->assignedTailor?->name ?? 'None';
        session()->flash('success', "Tailor assigned: {$tailorName}");
    }

    public function openDueDateModal(): void
    {
        $this->authorize('update', $this->order);

        $this->resetErrorBag('updatedDueDate');
        $this->updatedDueDate = (! $this->allowsOrderDatesFlexibility() && $this->order->due_date?->isBefore(today()))
            ? today()->toDateString()
            : ($this->order->due_date?->toDateString() ?? today()->toDateString());
        $this->showDueDateModal = true;
    }

    public function updateDueDate(): void
    {
        $this->authorize('update', $this->order);

        $rules = ['required', 'date'];
        if (! $this->allowsOrderDatesFlexibility()) {
            $rules[] = 'after_or_equal:today';
        }

        $validated = $this->validate([
            'updatedDueDate' => $rules,
        ]);

        $oldDueDate = $this->order->due_date?->toDateString();
        $newDueDate = Carbon::parse($validated['updatedDueDate'])->toDateString();

        if ($oldDueDate === $newDueDate) {
            $this->addError('updatedDueDate', __('Select a different due date to continue.'));

            return;
        }

        $this->order->update(['due_date' => $newDueDate]);
        $this->order->refresh();
        $this->loadOrderRelations();
        $this->showDueDateModal = false;

        event(new OrderDueDateChanged($this->order, $oldDueDate, $newDueDate, auth()->user()));

        session()->flash('success', __('Order due date updated to :date.', [
            'date' => $this->order->due_date?->format('M d, Y') ?? $newDueDate,
        ]));
    }

    public function updateStorefrontFulfillmentStatus(): void
    {
        $this->authorizeStorefrontOperations();

        if (! $this->order->isStorefrontOrder()) {
            session()->flash('error', 'Fulfillment status updates apply only to storefront orders.');

            return;
        }

        $validated = $this->validate([
            'newFulfillmentStatus' => ['required', 'string', Rule::in(array_column(StorefrontFulfillmentStatus::cases(), 'value'))],
            'fulfillmentNote' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = StorefrontFulfillmentStatus::from($validated['newFulfillmentStatus']);

        if ($this->order->fulfillment_status === $status) {
            session()->flash('success', 'Fulfillment status is already set to '.$status->label().'.');

            return;
        }

        $this->order->update([
            'fulfillment_status' => $status,
        ]);

        $this->order->statusHistory()->create([
            'status' => $status->value,
            'title' => 'Fulfillment: '.$status->label(),
            'note' => trim((string) ($validated['fulfillmentNote'] ?? '')) ?: null,
            'is_customer_visible' => true,
            'changed_by' => auth()->id(),
        ]);

        if (in_array($status, [StorefrontFulfillmentStatus::Shipped, StorefrontFulfillmentStatus::Delivered], true)) {
            $shipment = $this->order->currentShipment ?: new Shipment([
                'order_id' => $this->order->id,
                'created_by' => auth()->id(),
            ]);

            $shipment->status = $status === StorefrontFulfillmentStatus::Delivered ? 'delivered' : 'shipped';
            $shipment->shipping_method_code = $shipment->shipping_method_code ?: $this->order->shipping_method_code;
            $shipment->shipping_method_name = $shipment->shipping_method_name ?: $this->order->shipping_method_name;
            $shipment->updated_by = auth()->id();

            if ($status === StorefrontFulfillmentStatus::Shipped && ! $shipment->shipped_at) {
                $shipment->shipped_at = now();
            }

            if ($status === StorefrontFulfillmentStatus::Delivered && ! $shipment->delivered_at) {
                $shipment->delivered_at = now();
                $shipment->shipped_at = $shipment->shipped_at ?: now();
            }

            $shipment->save();
        }

        $this->notifyCustomer(
            new StorefrontOrderStatusUpdatedNotification(
                $this->order->fresh(['currentShipment']),
                $status,
                $validated['fulfillmentNote'] ?? null
            )
        );

        $this->order->refresh();
        $this->loadOrderRelations();
        $this->initializeStorefrontForms();

        session()->flash('success', 'Fulfillment status updated.');
    }

    public function saveShipmentDetails(): void
    {
        $this->authorizeStorefrontOperations();

        if (! $this->order->isStorefrontOrder()) {
            session()->flash('error', 'Shipment updates apply only to storefront orders.');

            return;
        }

        $validated = $this->validate([
            'shipmentStatus' => ['required', Rule::in(['pending', 'packed', 'shipped', 'delivered', 'cancelled'])],
            'shipmentCarrierName' => ['nullable', 'string', 'max:191'],
            'shipmentTrackingNumber' => ['nullable', 'string', 'max:191'],
            'shipmentTrackingUrl' => ['nullable', 'url', 'max:255'],
            'shipmentNotes' => ['nullable', 'string', 'max:2000'],
            'shipmentShippedAt' => ['nullable', 'date'],
            'shipmentDeliveredAt' => ['nullable', 'date', 'after_or_equal:shipmentShippedAt'],
        ]);

        $shipment = $this->order->currentShipment ?: new Shipment([
            'order_id' => $this->order->id,
            'created_by' => auth()->id(),
        ]);

        $shipment->status = $validated['shipmentStatus'];
        $shipment->shipping_method_code = $shipment->shipping_method_code ?: $this->order->shipping_method_code;
        $shipment->shipping_method_name = $shipment->shipping_method_name ?: $this->order->shipping_method_name;
        $shipment->carrier_name = $validated['shipmentCarrierName'] ?: null;
        $shipment->tracking_number = $validated['shipmentTrackingNumber'] ?: null;
        $shipment->tracking_url = $validated['shipmentTrackingUrl'] ?: null;
        $shipment->notes = $validated['shipmentNotes'] ?: null;
        $shipment->shipped_at = $validated['shipmentShippedAt'] ? Carbon::parse($validated['shipmentShippedAt']) : null;
        $shipment->delivered_at = $validated['shipmentDeliveredAt'] ? Carbon::parse($validated['shipmentDeliveredAt']) : null;
        $shipment->updated_by = auth()->id();

        if ($shipment->status === 'shipped' && ! $shipment->shipped_at) {
            $shipment->shipped_at = now();
        }

        if ($shipment->status === 'delivered') {
            $shipment->shipped_at = $shipment->shipped_at ?: now();
            $shipment->delivered_at = $shipment->delivered_at ?: now();
        }

        $shipment->save();

        $mappedFulfillment = match ($shipment->status) {
            'pending' => StorefrontFulfillmentStatus::Pending,
            'packed' => StorefrontFulfillmentStatus::ReadyForDispatch,
            'shipped' => StorefrontFulfillmentStatus::Shipped,
            'delivered' => StorefrontFulfillmentStatus::Delivered,
            'cancelled' => StorefrontFulfillmentStatus::Cancelled,
            default => null,
        };

        if ($mappedFulfillment && $this->order->fulfillment_status !== $mappedFulfillment) {
            $this->order->update(['fulfillment_status' => $mappedFulfillment]);

            $this->order->statusHistory()->create([
                'status' => $mappedFulfillment->value,
                'title' => 'Shipment: '.Str::headline($shipment->status),
                'note' => $shipment->notes,
                'is_customer_visible' => true,
                'changed_by' => auth()->id(),
            ]);
        }

        $this->notifyCustomer(
            new StorefrontShipmentUpdatedNotification(
                $this->order->fresh(['currentShipment']),
                $shipment->status,
                $shipment->tracking_number
            )
        );

        $this->order->refresh();
        $this->loadOrderRelations();
        $this->initializeStorefrontForms();

        session()->flash('success', 'Shipment details saved.');
    }

    public function publishCustomProgressUpdate(): void
    {
        $this->normalizeMoneyInputs();
        $this->authorizeStorefrontOperations();

        if ($this->order->order_type !== 'tailoring') {
            session()->flash('error', 'Custom progress updates apply only to tailoring orders.');

            return;
        }

        $stages = collect(config('storefront.custom_order_stages', []));
        $stageKeys = $stages->pluck('key')->filter()->all();

        $validated = $this->validate([
            'customStageKey' => ['required', 'string', Rule::in($stageKeys)],
            'customStageLabel' => ['nullable', 'string', 'max:191'],
            'customProgressNote' => ['nullable', 'string', 'max:2000'],
            'customProgressVisible' => ['boolean'],
            'customRequestedPaymentAmount' => ['nullable', 'numeric', 'min:0'],
            'customRequestedPaymentNote' => ['nullable', 'string', 'max:191'],
        ]);

        $configuredLabel = (string) ($stages->firstWhere('key', $validated['customStageKey'])['label'] ?? '');
        $stageLabel = trim((string) ($validated['customStageLabel'] ?: $configuredLabel ?: Str::headline($validated['customStageKey'])));

        $update = CustomOrderProgressUpdate::query()->create([
            'order_id' => $this->order->id,
            'stage_key' => $validated['customStageKey'],
            'stage_label' => $stageLabel,
            'note' => trim((string) ($validated['customProgressNote'] ?? '')) ?: null,
            'is_customer_visible' => (bool) $validated['customProgressVisible'],
            'requested_payment_amount' => $validated['customRequestedPaymentAmount'] ?: null,
            'requested_payment_note' => trim((string) ($validated['customRequestedPaymentNote'] ?? '')) ?: null,
            'updated_by' => auth()->id(),
        ]);

        $orderForNotification = $this->order->fresh(['customer', 'lines']);

        if ($update->is_customer_visible) {
            $this->notifyCustomer(
                new CustomOrderProgressUpdatedNotification(
                    $orderForNotification,
                    $update
                )
            );

            app(SmsService::class)->sendTemplate(
                'custom_order_progress_update',
                $orderForNotification->customer?->phone ?: $orderForNotification->checkout_phone,
                OrderSmsTemplates::replacementsForCustomProgressUpdate($orderForNotification, $update),
                $orderForNotification,
                auth()->user()
            );
        }

        $this->customStageLabel = '';
        $this->customProgressNote = '';
        $this->customProgressVisible = true;
        $this->customRequestedPaymentAmount = null;
        $this->customRequestedPaymentNote = '';

        $this->order->refresh();
        $this->loadOrderRelations();
        $this->initializeStorefrontForms();

        session()->flash('success', 'Custom progress update published.');
    }

    public function openDeliveryNoteModal(): void
    {
        $this->authorize('createDeliveryNote', $this->order);

        if ($this->order->hasOutstandingBalance()) {
            session()->flash('error', 'Cannot create delivery note until the order balance is fully paid.');

            return;
        }

        if (! $this->order->canCreateDeliveryNote()) {
            session()->flash('error', 'Cannot create delivery note for this order.');

            return;
        }

        $this->receivedByName = '';
        $this->receivedByPhone = '';
        $this->showDeliveryNoteModal = true;
    }

    public function createDeliveryNote(): void
    {
        $this->authorize('createDeliveryNote', $this->order);

        if ($this->order->hasOutstandingBalance()) {
            session()->flash('error', 'Cannot create delivery note until the order balance is fully paid.');

            return;
        }

        if (! $this->order->canCreateDeliveryNote()) {
            session()->flash('error', 'Cannot create delivery note for this order.');

            return;
        }

        try {
            $oldStatus = $this->order->status;
            $statusChanged = false;

            DB::transaction(function () use (&$statusChanged) {
                $deliveryNote = DeliveryNote::create([
                    'branch_id' => $this->order->branch_id,
                    'order_id' => $this->order->id,
                    'delivery_note_no' => DocNumber::deliveryNote(),
                    'delivered_at' => now(),
                    'delivered_by' => auth()->id(),
                    'received_by_name' => $this->receivedByName ?: null,
                    'received_by_phone' => $this->receivedByPhone ?: null,
                ]);

                // Update order status to delivered if not already
                if ($this->order->status !== OrderStatus::Delivered && $this->order->status !== OrderStatus::Completed) {
                    $this->order->update(['status' => OrderStatus::Delivered]);
                    $statusChanged = true;
                }
            });

            $this->order->refresh();
            $this->showDeliveryNoteModal = false;

            // Fire status changed event if status was updated
            if ($statusChanged) {
                event(new OrderStatusChanged($this->order, $oldStatus, OrderStatus::Delivered, auth()->user()));
            }

            session()->flash('success', 'Delivery note created successfully.');

        } catch (\Exception $e) {
            $this->addError('deliveryNote', 'Failed to create delivery note: '.$e->getMessage());
        }
    }

    /**
     * Get aggregated materials (fulfilled stock request items) for this order.
     * This shows what inventory items have been issued to the order.
     */
    public function getMaterialsProperty(): \Illuminate\Support\Collection
    {
        if (! $this->canViewMaterials) {
            return collect();
        }

        return OrderStockRequest::query()
            ->where('order_id', $this->order->id)
            ->with(['items.inventoryItem.stock'])
            ->get()
            ->flatMap(fn ($request) => $request->items)
            ->groupBy('inventory_item_id')
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'inventory_item' => $first->inventoryItem,
                    'qty_requested' => $items->sum('qty_requested'),
                    'qty_issued' => $items->sum('qty_issued'),
                    'pending' => $items->sum('qty_requested') - $items->sum('qty_issued'),
                ];
            })
            ->values();
    }

    /**
     * Get stock requests for this order.
     */
    public function getStockRequestsProperty(): \Illuminate\Support\Collection
    {
        if (! $this->canViewMaterials) {
            return collect();
        }

        return OrderStockRequest::query()
            ->where('order_id', $this->order->id)
            ->with(['items.inventoryItem', 'requester', 'handler'])
            ->latest()
            ->get();
    }

    protected function authorizeStorefrontOperations(): void
    {
        abort_unless(auth()->user()?->can('storefront.orders.manage'), 403);
    }

    protected function allowsOrderDatesFlexibility(): bool
    {
        return (bool) BusinessSetting::instance()->allow_order_dates_flexibility;
    }

    protected function notifyCustomer(Notification $notification): void
    {
        $this->order->loadMissing('customer.user');

        $customerUser = $this->order->customer?->user;

        if ($customerUser) {
            $customerUser->notify($notification);
        }
    }

    public function render()
    {
        $user = auth()->user();

        // Get available status transitions
        $nextStatuses = collect($this->order->getNextStatuses())
            ->mapWithKeys(fn ($status) => [$status->value => $status->label()]);

        // Get tailors for assignment
        $tailors = User::whereHas('roles', fn ($q) => $q->where('name', 'tailor'))
            ->where('branch_id', $this->order->branch_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $tailorNames = $this->order->involvedTailorNames();
        $tailorDisplay = $tailorNames->isNotEmpty() ? $tailorNames->implode(', ') : '-';

        // Order is in a final state (no more edits / stock requests)
        $orderIsFinal = in_array($this->order->status, [OrderStatus::Delivered, OrderStatus::Completed, OrderStatus::Cancelled]);

        // Determine available actions
        $canEdit = $user->can('update', $this->order) && ! $orderIsFinal;
        $canChangeStatus = $user->can('changeStatus', $this->order);
        $canAssignTailor = $user->can('assignTailor', $this->order);
        $showAssignTailorButton = $canAssignTailor && ! $this->order->hasTailorAssignments();
        $canMarkCompleted = $user->can('markCompleted', $this->order);
        $canCreateDeliveryNote = $user->can('createDeliveryNote', $this->order) && $this->order->canCreateDeliveryNote();
        $canDelete = $user->can('delete', $this->order);
        $canManageStorefrontOperations = $user->can('storefront.orders.manage');
        $isStorefrontOrder = $this->order->isStorefrontOrder();
        $isTailoringOrder = $this->order->order_type === 'tailoring';
        $orderInvoice = $this->canViewFinancials ? $this->order->invoice : null;
        $canViewInvoice = $orderInvoice !== null && $user->can('view', $orderInvoice);
        $visibleInvoice = $canViewInvoice ? $orderInvoice : null;

        $fulfillmentStatuses = collect(StorefrontFulfillmentStatus::cases())
            ->mapWithKeys(fn (StorefrontFulfillmentStatus $status) => [$status->value => $status->label()]);

        $customStageOptions = collect(config('storefront.custom_order_stages', []))
            ->mapWithKeys(fn (array $stage) => [$stage['key'] => $stage['label']]);

        return view('livewire.orders.show', [
            'nextStatuses' => $nextStatuses,
            'tailors' => $tailors,
            'canEdit' => $canEdit,
            'canChangeStatus' => $canChangeStatus,
            'canAssignTailor' => $canAssignTailor,
            'showAssignTailorButton' => $showAssignTailorButton,
            'orderIsFinal' => $orderIsFinal,
            'canMarkCompleted' => $canMarkCompleted,
            'canCreateDeliveryNote' => $canCreateDeliveryNote,
            'canDelete' => $canDelete,
            'tailorDisplay' => $tailorDisplay,
            'canManageStorefrontOperations' => $canManageStorefrontOperations,
            'isStorefrontOrder' => $isStorefrontOrder,
            'isTailoringOrder' => $isTailoringOrder,
            'fulfillmentStatuses' => $fulfillmentStatuses,
            'customStageOptions' => $customStageOptions,
            // Role-based visibility
            'canViewFinancials' => $this->canViewFinancials,
            'canViewMaterials' => $this->canViewMaterials,
            'canManageMaterials' => $this->canManageMaterials,
            'canViewPayments' => $this->canViewPayments,
            'canRecordPayments' => $this->canRecordPayments,
            'orderInvoice' => $visibleInvoice,
            'invoiceFinancialSummary' => $visibleInvoice ? $this->order->financialSummary() : null,
            // The canonical print route uses the same InvoicePolicy view authorization.
            'canPrintInvoice' => $canViewInvoice,
            'shipments' => $this->order->shipments()->latest('id')->get(),
            // Materials data for storekeeper
            'materials' => $this->materials,
            'stockRequests' => $this->stockRequests,
            'allowOrderDatesFlexibility' => $this->allowsOrderDatesFlexibility(),
            'orderPresentation' => app(OrderPackagePresenter::class)->forOrder($this->order),
        ])->title($this->getTitle());
    }
}

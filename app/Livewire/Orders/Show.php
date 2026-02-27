<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\DeliveryNote;
use App\Models\Order;
use App\Models\OrderStockRequest;
use App\Models\User;
use App\Services\Orders\OrderDeletionService;
use App\Support\DocNumber;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    public Order $order;

    // Role-based visibility flags
    public bool $canViewFinancials = false;
    public bool $canViewMaterials = false;
    public bool $canManageMaterials = false;
    public bool $canViewPayments = false;
    public bool $canRecordPayments = false;

    /**
     * Listen for payment-recorded event to refresh order data.
     */
    #[On('payment-recorded')]
    public function refreshOrderData(): void
    {
        $this->order->refresh();
        $this->loadOrderRelations();
    }

    /**
     * Listen for stock-request-updated event to refresh materials data.
     */
    #[On('stock-request-updated')]
    public function refreshMaterialsData(): void
    {
        $this->order->refresh();
    }

    // Modals
    public bool $showStatusModal = false;
    public bool $showAssignTailorModal = false;
    public bool $showDeliveryNoteModal = false;

    // Form data
    public string $newStatus = '';
    public ?int $selectedTailorId = null;
    public string $receivedByName = '';
    public string $receivedByPhone = '';

    public function mount(Order $order): void
    {
        $this->authorize('view', $order);
        $this->order = $order;
        $this->loadOrderRelations();
        $this->initializePermissions();
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
            'deliveryNote.deliveredBy',
            'invoice',
            'branch',
        ];

        // Only load payments if user can view them
        if (auth()->user()->can('payments.view')) {
            $relations[] = 'payments';
        }

        $this->order->load($relations);
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

        $tailorNames = collect();
        if ($this->order->assignedTailor?->name) {
            $tailorNames->push($this->order->assignedTailor->name);
        }

        $lineTailorNames = $this->order->lines
            ->pluck('assignedTailor.name')
            ->filter()
            ->unique()
            ->values();

        foreach ($lineTailorNames as $lineTailorName) {
            if (! $tailorNames->contains($lineTailorName)) {
                $tailorNames->push($lineTailorName);
            }
        }

        $tailorDisplay = $tailorNames->isNotEmpty() ? $tailorNames->implode(', ') : '—';

        // Order is in a final state (no more edits / stock requests)
        $orderIsFinal = in_array($this->order->status, [OrderStatus::Delivered, OrderStatus::Completed, OrderStatus::Cancelled]);

        // Determine available actions
        $canEdit = $user->can('update', $this->order) && ! $orderIsFinal;
        $canChangeStatus = $user->can('changeStatus', $this->order);
        $canAssignTailor = $user->can('assignTailor', $this->order);
        $showAssignTailorButton = $canAssignTailor && ! $this->order->assigned_tailor_id;
        $canMarkCompleted = $user->can('markCompleted', $this->order);
        $canCreateDeliveryNote = $user->can('createDeliveryNote', $this->order) && $this->order->canCreateDeliveryNote();
        $canDelete = $user->can('delete', $this->order);

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
            // Role-based visibility
            'canViewFinancials' => $this->canViewFinancials,
            'canViewMaterials' => $this->canViewMaterials,
            'canManageMaterials' => $this->canManageMaterials,
            'canViewPayments' => $this->canViewPayments,
            'canRecordPayments' => $this->canRecordPayments,
            // Materials data for storekeeper
            'materials' => $this->materials,
            'stockRequests' => $this->stockRequests,
        ])->title($this->getTitle());
    }
}

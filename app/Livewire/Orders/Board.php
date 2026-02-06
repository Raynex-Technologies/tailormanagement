<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Order Board')]
class Board extends Component
{
    #[Url]
    public string $search = '';

    public int $perColumn = 10;

    public int $newOffset = 0;
    public int $inProgressOffset = 0;
    public int $completedOffset = 0;

    public function updatedSearch(): void
    {
        $this->resetOffsets();
    }

    public function resetOffsets(): void
    {
        $this->newOffset = 0;
        $this->inProgressOffset = 0;
        $this->completedOffset = 0;
    }

    public function loadMoreNew(): void
    {
        $this->newOffset += $this->perColumn;
    }

    public function loadMoreInProgress(): void
    {
        $this->inProgressOffset += $this->perColumn;
    }

    public function loadMoreCompleted(): void
    {
        $this->completedOffset += $this->perColumn;
    }

    public function markCompleted(int $orderId): void
    {
        $order = Order::find($orderId);

        if (! $order) {
            session()->flash('error', 'Order not found.');
            return;
        }

        $this->authorize('markCompleted', $order);

        if (! $order->canTransitionTo(OrderStatus::Completed)) {
            session()->flash('error', 'This order cannot be marked as completed.');
            return;
        }

        $order->update(['status' => OrderStatus::Completed]);

        session()->flash('success', "Order {$order->order_no} marked as completed.");
    }

    protected function getOrdersQuery()
    {
        $user = auth()->user();

        $query = Order::query()
            ->with(['customer'])
            ->search($this->search);

        // For tailors, show only their assigned orders
        if ($user->hasRole('tailor')) {
            $query->forTailor($user->id);
        }

        return $query;
    }

    public function render()
    {
        $user = auth()->user();

        // Get counts for each column
        $baseQuery = $this->getOrdersQuery();

        // New Orders
        $newOrdersQuery = (clone $baseQuery)->newOrders();
        $newCount = $newOrdersQuery->count();
        $newOrders = $newOrdersQuery->latest()->limit($this->perColumn + $this->newOffset)->get();

        // In Progress Orders (includes in_progress and ready)
        $inProgressQuery = (clone $baseQuery)->inProgressGroup();
        $inProgressCount = $inProgressQuery->count();
        $inProgressOrders = $inProgressQuery->latest()->limit($this->perColumn + $this->inProgressOffset)->get();

        // Completed Orders (includes delivered and completed)
        $completedQuery = (clone $baseQuery)->completedGroup();
        $completedCount = $completedQuery->count();
        $completedOrders = $completedQuery->latest()->limit($this->perColumn + $this->completedOffset)->get();

        // Check if user can mark orders as completed
        $canMarkCompleted = $user->can('orders.mark_completed');

        return view('livewire.orders.board', [
            'newOrders' => $newOrders,
            'newCount' => $newCount,
            'newHasMore' => $newCount > count($newOrders),
            'inProgressOrders' => $inProgressOrders,
            'inProgressCount' => $inProgressCount,
            'inProgressHasMore' => $inProgressCount > count($inProgressOrders),
            'completedOrders' => $completedOrders,
            'completedCount' => $completedCount,
            'completedHasMore' => $completedCount > count($completedOrders),
            'canMarkCompleted' => $canMarkCompleted,
        ]);
    }
}

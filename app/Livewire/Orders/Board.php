<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app.sidebar')]
#[Title('Order Board')]
class Board extends Component
{
    private const BOARD_STATUSES = [
        OrderStatus::New,
        OrderStatus::InProgress,
        OrderStatus::Ready,
    ];

    #[Url]
    public string $search = '';

    public int $perColumn = 10;

    public int $newOffset = 0;
    public int $inProgressOffset = 0;
    public int $readyOffset = 0;

    public function updatedSearch(): void
    {
        $this->resetOffsets();
    }

    public function resetOffsets(): void
    {
        $this->newOffset = 0;
        $this->inProgressOffset = 0;
        $this->readyOffset = 0;
    }

    public function loadMoreNew(): void
    {
        $this->newOffset += $this->perColumn;
    }

    public function loadMoreInProgress(): void
    {
        $this->inProgressOffset += $this->perColumn;
    }

    public function loadMoreReady(): void
    {
        $this->readyOffset += $this->perColumn;
    }

    public function markCompleted(int $orderId): void
    {
        $order = Order::query()->find($orderId);

        if (! $order) {
            $this->pushToast('danger', 'Order not found.');
            return;
        }

        try {
            if (! $this->transitionOrderStatus($order, OrderStatus::Completed, 'markCompleted')) {
                return;
            }

            $this->pushToast('success', "Order {$order->order_no} marked as completed.");
        } catch (Throwable $exception) {
            report($exception);
            $this->pushToast('danger', 'Unable to mark order as completed.');
        }
    }

    public function moveOrder(int $orderId, string $targetStatus): void
    {
        $order = Order::query()->find($orderId);

        if (! $order) {
            $this->pushToast('danger', 'Order not found.');
            return;
        }

        $newStatus = OrderStatus::tryFrom($targetStatus);

        if (! $newStatus || ! in_array($newStatus, self::BOARD_STATUSES, true)) {
            $this->pushToast('danger', 'Invalid destination column.');
            return;
        }

        try {
            if (! $this->transitionOrderStatus($order, $newStatus, 'changeStatus')) {
                return;
            }

            $this->pushToast('success', "Order {$order->order_no} moved to {$newStatus->label()}.");
        } catch (Throwable $exception) {
            report($exception);
            $this->pushToast('danger', 'Unable to move this order.');
        }
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
        // Get counts for each column
        $baseQuery = $this->getOrdersQuery();

        // New Orders
        $newOrdersQuery = (clone $baseQuery)->newOrders();
        $newCount = $newOrdersQuery->count();
        $newOrders = $newOrdersQuery->latest()->limit($this->perColumn + $this->newOffset)->get();

        // In Progress Orders
        $inProgressQuery = (clone $baseQuery)->inProgressGroup();
        $inProgressCount = $inProgressQuery->count();
        $inProgressOrders = $inProgressQuery->latest()->limit($this->perColumn + $this->inProgressOffset)->get();

        // Ready Orders
        $readyQuery = (clone $baseQuery)->readyGroup();
        $readyCount = $readyQuery->count();
        $readyOrders = $readyQuery->latest()->limit($this->perColumn + $this->readyOffset)->get();

        return view('livewire.orders.board', [
            'newOrders' => $newOrders,
            'newCount' => $newCount,
            'newHasMore' => $newCount > count($newOrders),
            'inProgressOrders' => $inProgressOrders,
            'inProgressCount' => $inProgressCount,
            'inProgressHasMore' => $inProgressCount > count($inProgressOrders),
            'readyOrders' => $readyOrders,
            'readyCount' => $readyCount,
            'readyHasMore' => $readyCount > count($readyOrders),
        ]);
    }

    protected function transitionOrderStatus(Order $order, OrderStatus $newStatus, string $ability): bool
    {
        $this->authorize($ability, $order);

        if ($order->status === $newStatus) {
            $this->pushToast('warning', 'Order is already in that column.');

            return false;
        }

        if (! $order->canTransitionTo($newStatus)) {
            $this->pushToast('danger', 'Invalid status transition.');

            return false;
        }

        $oldStatus = $order->status;

        $order->update(['status' => $newStatus]);
        $order->refresh();

        event(new OrderStatusChanged($order, $oldStatus, $newStatus, auth()->user()));

        return true;
    }

    protected function pushToast(string $variant, string $text): void
    {
        $this->dispatch('board-toast', variant: $variant, text: $text);
    }
}

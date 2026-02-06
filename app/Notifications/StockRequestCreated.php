<?php

namespace App\Notifications;

use App\Models\OrderStockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StockRequestCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrderStockRequest $stockRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $order = $this->stockRequest->order;
        $requester = $this->stockRequest->requester;

        return [
            'type' => 'stock_request_created',
            'stock_request_id' => $this->stockRequest->id,
            'order_id' => $order?->id,
            'order_no' => $order?->order_no,
            'requester_name' => $requester?->name ?? 'Unknown',
            'items_count' => $this->stockRequest->items()->count(),
            'message' => "New stock request from {$requester?->name} for Order #{$order?->order_no}",
            'action_url' => route('store.stock-requests.show', $this->stockRequest),
        ];
    }
}

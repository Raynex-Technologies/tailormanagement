<?php

namespace App\Notifications;

use App\Models\OrderStockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StockRequestReviewed extends Notification implements ShouldQueue
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
        $handler = $this->stockRequest->handler;
        $status = $this->stockRequest->status->label();
        $statusLower = strtolower($status);

        return [
            'type' => 'stock_request_reviewed',
            'stock_request_id' => $this->stockRequest->id,
            'order_id' => $order?->id,
            'order_no' => $order?->order_no,
            'status' => $this->stockRequest->status->value,
            'handler_name' => $handler?->name ?? 'Unknown',
            'message' => "Your stock request for Order #{$order?->order_no} has been {$statusLower} by {$handler?->name}",
            'action_url' => route('orders.stock-requests', $order),
        ];
    }
}

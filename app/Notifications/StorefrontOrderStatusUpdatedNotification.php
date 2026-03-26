<?php

namespace App\Notifications;

use App\Enums\StorefrontFulfillmentStatus;
use App\Models\Order;
use Illuminate\Notifications\Notification;

class StorefrontOrderStatusUpdatedNotification extends Notification
{
    public function __construct(
        public Order $order,
        public StorefrontFulfillmentStatus $status,
        public ?string $note = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusLabel = $this->status->label();
        $body = "Your order #{$this->order->order_no} is now {$statusLabel}.";

        if (filled($this->note)) {
            $body .= ' '.$this->note;
        }

        return [
            'title' => 'Order Status Updated',
            'body' => $body,
            'link' => route('storefront.account.orders.show', $this->order),
            'icon' => 'truck',
            'color' => $this->status->color(),
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'fulfillment_status' => $this->status->value,
            'fulfillment_status_label' => $statusLabel,
            'note' => $this->note,
        ];
    }
}


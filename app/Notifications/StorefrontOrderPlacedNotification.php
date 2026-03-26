<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class StorefrontOrderPlacedNotification extends Notification
{
    public function __construct(
        public Order $order,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order Placed',
            'body' => "Your order #{$this->order->order_no} has been placed successfully.",
            'link' => route('storefront.account.orders.show', $this->order),
            'icon' => 'shopping-bag',
            'color' => 'blue',
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'amount' => $this->order->payableTotal(),
            'currency' => $this->order->currency,
        ];
    }
}


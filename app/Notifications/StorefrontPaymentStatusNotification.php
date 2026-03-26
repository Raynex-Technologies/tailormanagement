<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Notifications\Notification;

class StorefrontPaymentStatusNotification extends Notification
{
    public function __construct(
        public Order $order,
        public PaymentTransaction $transaction,
        public bool $successful,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $orderRoute = $this->order->order_type === 'tailoring'
            ? route('storefront.account.custom-orders.show', $this->order)
            : route('storefront.account.orders.show', $this->order);

        return [
            'title' => $this->successful ? 'Payment Successful' : 'Payment Failed',
            'body' => $this->successful
                ? "Payment for order #{$this->order->order_no} was confirmed."
                : "Payment for order #{$this->order->order_no} failed. You can retry from your account.",
            'link' => $orderRoute,
            'icon' => $this->successful ? 'check-circle' : 'x-circle',
            'color' => $this->successful ? 'green' : 'red',
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'transaction_id' => $this->transaction->id,
            'merchant_reference' => $this->transaction->merchant_reference,
            'gateway_reference' => $this->transaction->gateway_reference,
            'amount' => $this->transaction->amount,
            'currency' => $this->transaction->currency,
            'status' => $this->transaction->status?->value,
        ];
    }
}


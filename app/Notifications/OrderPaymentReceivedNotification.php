<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderPaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public OrderPayment $payment
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $amount = money_tzs($this->payment->amount);
        $balance = money_tzs($this->order->balance_amount);
        $receiverName = $this->payment->receiver?->name ?? 'Unknown';

        return [
            'title' => 'Payment Received',
            'body' => "Payment of {$amount} received for Order #{$this->order->order_no} by {$receiverName}. Balance: {$balance}.",
            'link' => route('orders.show', $this->order),
            'icon' => 'banknotes',
            'color' => 'green',
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'amount' => $this->payment->amount,
            'balance' => $this->order->balance_amount,
        ];
    }
}

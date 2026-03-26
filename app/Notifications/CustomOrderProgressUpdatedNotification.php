<?php

namespace App\Notifications;

use App\Models\CustomOrderProgressUpdate;
use App\Models\Order;
use Illuminate\Notifications\Notification;

class CustomOrderProgressUpdatedNotification extends Notification
{
    public function __construct(
        public Order $order,
        public CustomOrderProgressUpdate $progressUpdate,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $body = "Your custom order #{$this->order->order_no} has a new progress update: {$this->progressUpdate->stage_label}.";

        if (filled($this->progressUpdate->note)) {
            $body .= ' '.$this->progressUpdate->note;
        }

        if ($this->progressUpdate->requested_payment_amount) {
            $body .= ' Payment requested: '.money_currency($this->progressUpdate->requested_payment_amount, $this->order->currency ?: 'TZS').'.';
        }

        return [
            'title' => 'Custom Order Progress Updated',
            'body' => $body,
            'link' => route('storefront.account.custom-orders.show', $this->order),
            'icon' => 'sparkles',
            'color' => 'violet',
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'stage_key' => $this->progressUpdate->stage_key,
            'stage_label' => $this->progressUpdate->stage_label,
            'note' => $this->progressUpdate->note,
            'requested_payment_amount' => $this->progressUpdate->requested_payment_amount,
            'requested_payment_note' => $this->progressUpdate->requested_payment_note,
            'progress_update_id' => $this->progressUpdate->id,
        ];
    }
}


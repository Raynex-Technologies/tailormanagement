<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class StorefrontShipmentUpdatedNotification extends Notification
{
    public function __construct(
        public Order $order,
        public string $shipmentStatus,
        public ?string $trackingNumber = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusLabel = Str::headline($this->shipmentStatus);
        $body = "Shipment update for order #{$this->order->order_no}: {$statusLabel}.";

        if (filled($this->trackingNumber)) {
            $body .= " Tracking number: {$this->trackingNumber}.";
        }

        return [
            'title' => 'Shipment Updated',
            'body' => $body,
            'link' => route('storefront.account.orders.show', $this->order),
            'icon' => 'truck',
            'color' => in_array($this->shipmentStatus, ['delivered', 'shipped'], true) ? 'green' : 'blue',
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'shipment_status' => $this->shipmentStatus,
            'tracking_number' => $this->trackingNumber,
        ];
    }
}


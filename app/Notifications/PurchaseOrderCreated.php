<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PurchaseOrderCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PurchaseOrder $purchaseOrder
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $total = money_tzs($this->purchaseOrder->total);
        $supplierName = $this->purchaseOrder->supplier?->name ?? 'Unknown';
        $creatorName = $this->purchaseOrder->creator?->name ?? 'Unknown';

        return [
            'title' => 'Purchase Order Created',
            'body' => "PO #{$this->purchaseOrder->po_no} for {$supplierName} created by {$creatorName}. Total: {$total}",
            'link' => route('procurement.pos.show', $this->purchaseOrder),
            'icon' => 'shopping-cart',
            'color' => 'purple',
            'purchase_order_id' => $this->purchaseOrder->id,
            'po_no' => $this->purchaseOrder->po_no,
        ];
    }
}

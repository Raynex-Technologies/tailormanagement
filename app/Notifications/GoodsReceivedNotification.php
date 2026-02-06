<?php

namespace App\Notifications;

use App\Models\GoodsReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GoodsReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public GoodsReceipt $goodsReceipt
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $receiverName = $this->goodsReceipt->receiver?->name ?? 'Unknown';
        $poNo = $this->goodsReceipt->purchaseOrder?->po_no ?? 'N/A';
        $itemCount = $this->goodsReceipt->items()->count();

        return [
            'title' => 'Goods Received',
            'body' => "GRN #{$this->goodsReceipt->grn_no}: {$itemCount} item(s) received for PO #{$poNo} by {$receiverName}.",
            'link' => route('procurement.receiving.show', $this->goodsReceipt->purchaseOrder),
            'icon' => 'truck',
            'color' => 'green',
            'goods_receipt_id' => $this->goodsReceipt->id,
            'grn_no' => $this->goodsReceipt->grn_no,
            'po_no' => $poNo,
        ];
    }
}

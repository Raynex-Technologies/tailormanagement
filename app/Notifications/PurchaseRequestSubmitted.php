<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PurchaseRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PurchaseRequest $purchaseRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $total = money_tzs($this->purchaseRequest->estimated_total);
        $requesterName = $this->purchaseRequest->requester?->name ?? 'Unknown';

        return [
            'title' => 'Purchase Request Submitted',
            'body' => "PR #{$this->purchaseRequest->request_no} submitted by {$requesterName} for {$total}. Please review.",
            'link' => route('procurement.requests.show', $this->purchaseRequest),
            'icon' => 'clipboard-document-check',
            'color' => 'blue',
            'purchase_request_id' => $this->purchaseRequest->id,
            'request_no' => $this->purchaseRequest->request_no,
        ];
    }
}

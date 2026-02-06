<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PurchaseRequestReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PurchaseRequest $purchaseRequest,
        public string $decision
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $reviewerName = $this->purchaseRequest->reviewer?->name ?? 'Unknown';
        $statusLabel = ucfirst($this->decision);
        $isApproved = $this->decision === 'approved';

        return [
            'title' => "Purchase Request {$statusLabel}",
            'body' => "PR #{$this->purchaseRequest->request_no} has been {$this->decision} by {$reviewerName}.",
            'link' => route('procurement.requests.show', $this->purchaseRequest),
            'icon' => $isApproved ? 'check-circle' : 'x-circle',
            'color' => $isApproved ? 'green' : 'red',
            'purchase_request_id' => $this->purchaseRequest->id,
            'request_no' => $this->purchaseRequest->request_no,
            'decision' => $this->decision,
        ];
    }
}

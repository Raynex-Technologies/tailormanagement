<?php

namespace App\Listeners;

use App\Events\OrderPaymentRecorded;
use App\Services\Mail\CustomerEmailDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderPaymentEmail implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function handle(OrderPaymentRecorded $event): void
    {
        app(CustomerEmailDeliveryService::class)->sendPaymentReceived($event->order, $event->payment);
    }
}

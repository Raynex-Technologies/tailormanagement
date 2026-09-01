<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\Mail\CustomerEmailDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderCreatedEmail implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function handle(OrderCreated $event): void
    {
        app(CustomerEmailDeliveryService::class)->sendOrderCreated($event->order);
    }
}

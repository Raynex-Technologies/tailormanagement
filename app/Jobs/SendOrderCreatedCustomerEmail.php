<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Scopes\BranchScope;
use App\Services\Mail\CustomerEmailDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderCreatedCustomerEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $orderId,
    ) {}

    public function handle(CustomerEmailDeliveryService $deliveryService): void
    {
        $order = Order::query()
            ->withoutGlobalScope(BranchScope::class)
            ->findOrFail($this->orderId);

        $deliveryService->sendOrderCreated($order);
    }
}

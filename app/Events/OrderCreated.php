<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public ?User $actor = null,
        public ?OrderPayment $deposit = null
    ) {}
}

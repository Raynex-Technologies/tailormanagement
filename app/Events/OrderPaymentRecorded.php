<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaymentRecorded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public OrderPayment $payment,
        public User $actor,
        public bool $sendCustomerSms = true
    ) {}
}

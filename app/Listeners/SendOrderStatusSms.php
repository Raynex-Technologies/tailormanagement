<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\OrderSmsTemplates;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOrderStatusSms implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected SmsService $smsService) {}

    /**
     * Handle the event.
     */
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;
        $newStatus = $event->newStatus->value;

        // Only send SMS for specific statuses
        if (! OrderSmsTemplates::shouldNotifyForStatus($newStatus)) {
            Log::info('Skipping SMS for order status change', [
                'order_id' => $order->id,
                'new_status' => $newStatus,
            ]);

            return;
        }

        // Get customer phone
        $customerPhone = $order->customer?->phone;

        // Generate message
        $message = OrderSmsTemplates::statusChanged($order, $newStatus);

        // Send SMS (will log failure if phone is missing)
        $this->smsService->sendIfPhonePresent(
            $customerPhone,
            $message,
            $order,
            $event->actor
        );
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(OrderStatusChanged $event): bool
    {
        return OrderSmsTemplates::shouldNotifyForStatus($event->newStatus->value);
    }
}

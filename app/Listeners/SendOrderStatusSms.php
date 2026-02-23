<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\OrderSmsTemplates;
use Illuminate\Support\Facades\Log;

class SendOrderStatusSms
{
    public function __construct(protected SmsService $smsService) {}

    /**
     * Handle the event.
     */
    public function handle(OrderStatusChanged $event): void
    {
        try {
            $order = $event->order;
            $newStatus = $event->newStatus->value;

            Log::info('SendOrderStatusSms handling', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'new_status' => $newStatus,
                'will_notify' => OrderSmsTemplates::shouldNotifyForStatus($newStatus),
            ]);

            // Only send SMS for specific statuses
            if (! OrderSmsTemplates::shouldNotifyForStatus($newStatus)) {
                Log::debug('Skipping SMS for order status change (status not in notifiable list)', [
                    'order_id' => $order->id,
                    'new_status' => $newStatus,
                ]);

                return;
            }

            // Get customer phone
            $customerPhone = $order->customer?->phone;
            Log::debug('Order status SMS', ['order_id' => $order->id, 'has_phone' => ! empty($customerPhone)]);

            // Generate message
            $message = OrderSmsTemplates::statusChanged($order, $newStatus);

            // Send SMS (will log failure if phone is missing)
            $this->smsService->sendIfPhonePresent(
                $customerPhone,
                $message,
                $order,
                $event->actor
            );
        } catch (\Throwable $e) {
            Log::error('SendOrderStatusSms failed', [
                'order_id' => $event->order->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

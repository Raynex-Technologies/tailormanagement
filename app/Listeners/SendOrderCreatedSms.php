<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\OrderSmsTemplates;
use Illuminate\Support\Facades\Log;

class SendOrderCreatedSms
{
    public function __construct(protected SmsService $smsService) {}

    public function handle(OrderCreated $event): void
    {
        try {
            $order = $event->order;
            $customerPhone = $order->customer?->phone;

            Log::info('SendOrderCreatedSms handling', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'has_phone' => ! empty($customerPhone),
                'phone' => $customerPhone ? substr($customerPhone, 0, 4).'***' : null,
            ]);

            $this->smsService->sendTemplate(
                'order_created',
                $customerPhone,
                OrderSmsTemplates::replacementsForOrder($order, $event->deposit),
                $order,
                $event->actor
            );
        } catch (\Throwable $e) {
            Log::error('SendOrderCreatedSms failed', [
                'order_id' => $event->order->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

<?php

namespace App\Listeners;

use App\Events\OrderDueDateChanged;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\OrderSmsTemplates;
use Illuminate\Support\Facades\Log;

class SendOrderDueDateChangedSms
{
    public function __construct(protected SmsService $smsService) {}

    public function handle(OrderDueDateChanged $event): void
    {
        try {
            $order = $event->order;
            $customerPhone = $order->customer?->phone;

            Log::info('SendOrderDueDateChangedSms handling', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'old_due_date' => $event->oldDueDate,
                'new_due_date' => $event->newDueDate,
                'has_phone' => ! empty($customerPhone),
            ]);

            $message = OrderSmsTemplates::dueDateChanged($order, $event->oldDueDate, $event->newDueDate);

            $this->smsService->sendIfPhonePresent(
                $customerPhone,
                $message,
                $order,
                $event->actor
            );
        } catch (\Throwable $e) {
            Log::error('SendOrderDueDateChangedSms failed', [
                'order_id' => $event->order->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}

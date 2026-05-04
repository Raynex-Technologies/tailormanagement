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

            $replacements = OrderSmsTemplates::replacementsForOrder($order);
            $replacements['old_due_date'] = OrderSmsTemplates::formatDateValue($event->oldDueDate);
            $replacements['new_due_date'] = OrderSmsTemplates::formatDateValue($event->newDueDate);
            $replacements['due_date'] = $replacements['new_due_date'];
            $replacements['expected_delivery_date'] = $replacements['new_due_date'];

            $this->smsService->sendTemplate(
                'order_delivery_date_change',
                $customerPhone,
                $replacements,
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

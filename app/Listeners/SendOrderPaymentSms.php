<?php

namespace App\Listeners;

use App\Events\OrderPaymentRecorded;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\OrderSmsTemplates;
use Illuminate\Support\Facades\Log;

class SendOrderPaymentSms
{
    public function __construct(protected SmsService $smsService) {}

    /**
     * Handle the event.
     */
    public function handle(OrderPaymentRecorded $event): void
    {
        if (! $event->sendCustomerSms) {
            return;
        }

        try {
            $order = $event->order;
            $payment = $event->payment;

            Log::info('SendOrderPaymentSms handling', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ]);

            // Get customer phone
            $customerPhone = $order->customer?->phone;
            Log::debug('Order payment SMS', ['order_id' => $order->id, 'has_phone' => ! empty($customerPhone)]);

            $this->smsService->sendTemplate(
                'order_payment',
                $customerPhone,
                OrderSmsTemplates::replacementsForOrder($order, $payment),
                $order,
                $event->actor
            );
        } catch (\Throwable $e) {
            Log::error('SendOrderPaymentSms failed', [
                'order_id' => $event->order->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

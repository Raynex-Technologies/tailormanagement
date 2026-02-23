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

            // Generate message
            $message = OrderSmsTemplates::paymentReceived($order, $payment);

            // Send SMS (will log failure if phone is missing)
            $this->smsService->sendIfPhonePresent(
                $customerPhone,
                $message,
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

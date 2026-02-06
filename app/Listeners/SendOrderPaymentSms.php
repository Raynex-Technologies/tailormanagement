<?php

namespace App\Listeners;

use App\Events\OrderPaymentRecorded;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\OrderSmsTemplates;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderPaymentSms implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected SmsService $smsService) {}

    /**
     * Handle the event.
     */
    public function handle(OrderPaymentRecorded $event): void
    {
        $order = $event->order;
        $payment = $event->payment;

        // Get customer phone
        $customerPhone = $order->customer?->phone;

        // Generate message
        $message = OrderSmsTemplates::paymentReceived($order, $payment);

        // Send SMS (will log failure if phone is missing)
        $this->smsService->sendIfPhonePresent(
            $customerPhone,
            $message,
            $order,
            $event->actor
        );
    }
}

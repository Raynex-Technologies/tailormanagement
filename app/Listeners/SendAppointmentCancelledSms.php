<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\AppointmentSmsTemplates;

class SendAppointmentCancelledSms
{
    public function __construct(protected SmsService $smsService) {}

    public function handle(AppointmentCancelled $event): void
    {
        $appointment = $event->appointment->fresh() ?? $event->appointment;

        $this->smsService->sendTemplate(
            'appointment_cancelled',
            $appointment->customer?->phone ?: $appointment->customer_phone,
            AppointmentSmsTemplates::replacementsForAppointment($appointment),
            $appointment
        );
    }
}

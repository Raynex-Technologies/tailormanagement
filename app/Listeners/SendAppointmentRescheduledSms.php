<?php

namespace App\Listeners;

use App\Events\AppointmentRescheduled;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\AppointmentSmsTemplates;

class SendAppointmentRescheduledSms
{
    public function __construct(protected SmsService $smsService) {}

    public function handle(AppointmentRescheduled $event): void
    {
        $appointment = $event->appointment->fresh() ?? $event->appointment;

        $this->smsService->sendTemplate(
            'appointment_rescheduled',
            $appointment->customer?->phone ?: $appointment->customer_phone,
            AppointmentSmsTemplates::replacementsForAppointment($appointment),
            $appointment
        );
    }
}

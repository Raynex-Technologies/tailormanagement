<?php

namespace App\Listeners;

use App\Events\AppointmentRequested;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\AppointmentSmsTemplates;

class SendAppointmentCreatedSms
{
    public function __construct(protected SmsService $smsService) {}

    public function handle(AppointmentRequested $event): void
    {
        $appointment = $event->appointment->fresh() ?? $event->appointment;

        $this->smsService->sendTemplate(
            'appointment_created',
            $appointment->customer?->phone ?: $appointment->customer_phone,
            AppointmentSmsTemplates::replacementsForAppointment($appointment),
            $appointment
        );
    }
}

<?php

namespace App\Listeners;

use App\Events\AppointmentApproved;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\AppointmentSmsTemplates;

class SendAppointmentConfirmedSms
{
    public function __construct(protected SmsService $smsService) {}

    public function handle(AppointmentApproved $event): void
    {
        $appointment = $event->appointment->fresh() ?? $event->appointment;

        $this->smsService->sendTemplate(
            'appointment_confirmed',
            $appointment->customer?->phone ?: $appointment->customer_phone,
            AppointmentSmsTemplates::replacementsForAppointment($appointment),
            $appointment
        );
    }
}

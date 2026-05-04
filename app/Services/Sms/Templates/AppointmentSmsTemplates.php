<?php

namespace App\Services\Sms\Templates;

use App\Models\Appointment;

class AppointmentSmsTemplates
{
    public static function replacementsForAppointment(Appointment $appointment): array
    {
        $appointment->loadMissing(['customer']);

        return [
            'customer_name' => $appointment->customer?->name ?: $appointment->customer_name ?: 'Customer',
            'appointment_date' => $appointment->scheduled_start_at?->format('M d, Y') ?? '',
            'appointment_time' => $appointment->scheduled_start_at?->format('H:i') ?? '',
        ];
    }
}

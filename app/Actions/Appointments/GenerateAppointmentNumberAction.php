<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;

class GenerateAppointmentNumberAction
{
    public function execute(): string
    {
        $next = Appointment::query()->count() + 1;

        do {
            $number = sprintf('TPA-%06d', $next++);
        } while (Appointment::query()->where('appointment_number', $number)->exists());

        return $number;
    }
}

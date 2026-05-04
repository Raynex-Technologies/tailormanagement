<?php

namespace App\Actions\Appointments;

use App\Events\AppointmentCancelled;
use App\Models\Appointment;

class CancelAppointmentAction
{
    public function execute(Appointment $appointment, string $reason, ?int $userId = null): Appointment
    {
        $old = $appointment->status;
        $appointment->forceFill([
            'status' => 'cancelled',
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        $appointment->statusHistories()->create([
            'old_status' => $old,
            'new_status' => 'cancelled',
            'changed_by' => $userId,
            'note' => $reason,
        ]);

        event(new AppointmentCancelled($appointment->fresh()));

        return $appointment;
    }
}

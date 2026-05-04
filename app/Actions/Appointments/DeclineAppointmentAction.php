<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;

class DeclineAppointmentAction
{
    public function execute(Appointment $appointment, string $reason, ?int $userId = null): Appointment
    {
        $old = $appointment->status;
        $appointment->forceFill([
            'status' => 'declined',
            'declined_by' => $userId,
            'declined_at' => now(),
            'decline_reason' => $reason,
        ])->save();

        $appointment->statusHistories()->create([
            'old_status' => $old,
            'new_status' => 'declined',
            'changed_by' => $userId,
            'note' => $reason,
        ]);

        return $appointment;
    }
}

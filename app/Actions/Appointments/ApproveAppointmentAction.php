<?php

namespace App\Actions\Appointments;

use App\Events\AppointmentApproved;
use App\Models\Appointment;
use App\Services\Appointments\AppointmentAvailabilityService;
use Illuminate\Validation\ValidationException;

class ApproveAppointmentAction
{
    public function __construct(protected AppointmentAvailabilityService $availability) {}

    public function execute(Appointment $appointment, ?int $userId = null): Appointment
    {
        if (! $this->availability->isSlotAvailable(
            $appointment->branch_id,
            $appointment->appointment_type_id,
            $appointment->scheduled_start_at->toIso8601String(),
            $appointment->scheduled_end_at->toIso8601String(),
            null,
            $appointment->id
        )) {
            throw ValidationException::withMessages(['appointment' => 'This slot is no longer available.']);
        }

        $old = $appointment->status;
        $appointment->forceFill([
            'status' => 'confirmed',
            'approved_by' => $userId,
            'approved_at' => now(),
        ])->save();

        $appointment->statusHistories()->create([
            'old_status' => $old,
            'new_status' => 'confirmed',
            'changed_by' => $userId,
            'note' => 'Appointment approved.',
        ]);

        event(new AppointmentApproved($appointment->fresh()));

        return $appointment;
    }
}

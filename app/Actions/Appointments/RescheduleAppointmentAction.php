<?php

namespace App\Actions\Appointments;

use App\Events\AppointmentRescheduled;
use App\Models\Appointment;
use App\Services\Appointments\AppointmentAvailabilityService;
use Illuminate\Validation\ValidationException;

class RescheduleAppointmentAction
{
    public function __construct(protected AppointmentAvailabilityService $availability) {}

    public function execute(Appointment $appointment, string $startAt, string $endAt, ?string $note = null, ?int $userId = null): Appointment
    {
        if (! $this->availability->isSlotAvailable($appointment->branch_id, $appointment->appointment_type_id, $startAt, $endAt, null, $appointment->id)) {
            throw ValidationException::withMessages(['rescheduleStartAt' => 'The selected reschedule slot is not available.']);
        }

        $old = $appointment->status;
        $appointment->forceFill([
            'scheduled_start_at' => $startAt,
            'scheduled_end_at' => $endAt,
            'status' => 'rescheduled',
        ])->save();

        $appointment->statusHistories()->create([
            'old_status' => $old,
            'new_status' => 'rescheduled',
            'changed_by' => $userId,
            'note' => $note ?: 'Appointment rescheduled.',
        ]);

        event(new AppointmentRescheduled($appointment->fresh()));

        return $appointment;
    }
}

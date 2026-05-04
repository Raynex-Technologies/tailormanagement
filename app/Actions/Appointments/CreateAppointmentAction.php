<?php

namespace App\Actions\Appointments;

use App\Events\AppointmentApproved;
use App\Events\AppointmentRequested;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Services\Appointments\AppointmentAvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAppointmentAction
{
    public function __construct(
        protected AppointmentAvailabilityService $availability,
        protected GenerateAppointmentNumberAction $numbers,
    ) {}

    public function execute(array $data): Appointment
    {
        $type = AppointmentType::query()->findOrFail($data['appointment_type_id']);

        if (! $this->availability->isSlotAvailable(
            $data['branch_id'] ?? null,
            (int) $data['appointment_type_id'],
            $data['scheduled_start_at'],
            $data['scheduled_end_at'],
        )) {
            throw ValidationException::withMessages([
                'selectedSlot' => 'That appointment slot is no longer available. Please choose another time.',
            ]);
        }

        return DB::transaction(function () use ($data, $type): Appointment {
            $appointment = Appointment::query()->create(array_merge($data, [
                'appointment_number' => $this->numbers->execute(),
                'status' => $type->requires_approval ? 'pending_approval' : 'confirmed',
            ]));

            $appointment->statusHistories()->create([
                'old_status' => null,
                'new_status' => $appointment->status,
                'changed_by' => auth()->id(),
                'note' => 'Appointment created.',
            ]);

            event($appointment->status === 'confirmed'
                ? new AppointmentApproved($appointment->fresh())
                : new AppointmentRequested($appointment->fresh()));

            return $appointment;
        });
    }
}

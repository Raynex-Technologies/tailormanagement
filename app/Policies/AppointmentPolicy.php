<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('appointments.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.view')
            && ($user->isGlobalAdmin() || $appointment->branch_id === null || $appointment->branch_id === $user->branch_id);
    }

    public function manage(User $user): bool
    {
        return $user->can('appointments.manage');
    }

    public function approve(User $user): bool
    {
        return $user->can('appointments.approve');
    }

    public function decline(User $user): bool
    {
        return $user->can('appointments.decline');
    }

    public function reschedule(User $user): bool
    {
        return $user->can('appointments.reschedule');
    }

    public function cancel(User $user): bool
    {
        return $user->can('appointments.cancel');
    }

    public function complete(User $user): bool
    {
        return $user->can('appointments.complete');
    }
}

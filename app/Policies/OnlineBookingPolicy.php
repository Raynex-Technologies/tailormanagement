<?php

namespace App\Policies;

use App\Models\OnlineBooking;
use App\Models\User;

class OnlineBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('online-bookings.view');
    }

    public function view(User $user, OnlineBooking $booking): bool
    {
        return $user->can('online-bookings.view')
            && ($user->isGlobalAdmin() || $booking->branch_id === null || $booking->branch_id === $user->branch_id);
    }

    public function manage(User $user): bool
    {
        return $user->can('online-bookings.manage');
    }

    public function review(User $user): bool
    {
        return $user->can('online-bookings.review');
    }

    public function convert(User $user): bool
    {
        return $user->can('online-bookings.convert');
    }
}

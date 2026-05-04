<?php

namespace App\Actions\Bookings;

use App\Models\OnlineBooking;

class UpdateOnlineBookingStatusAction
{
    public function execute(OnlineBooking $booking, string $status, ?string $note = null, ?int $userId = null): OnlineBooking
    {
        $booking->forceFill([
            'status' => $status,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'decline_reason' => $status === 'declined' ? $note : $booking->decline_reason,
            'internal_note' => $note ?: $booking->internal_note,
        ])->save();

        return $booking;
    }
}

<?php

namespace App\Actions\Bookings;

use App\Models\OnlineBooking;

class GenerateBookingNumberAction
{
    public function execute(): string
    {
        $next = OnlineBooking::withTrashed()->count() + 1;

        do {
            $number = sprintf('TPB-%06d', $next++);
        } while (OnlineBooking::withTrashed()->where('booking_number', $number)->exists());

        return $number;
    }
}

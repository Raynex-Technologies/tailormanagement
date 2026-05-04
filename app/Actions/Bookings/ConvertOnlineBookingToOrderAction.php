<?php

namespace App\Actions\Bookings;

use App\Models\OnlineBooking;
use Illuminate\Validation\ValidationException;

class ConvertOnlineBookingToOrderAction
{
    public function execute(OnlineBooking $booking): void
    {
        // TODO: Map booking items, measurements, pricing, deposits, and assignment
        // into the existing production order workflow after business rules are confirmed.
        throw ValidationException::withMessages([
            'conversion' => 'Online booking conversion is prepared but not enabled until production order mapping rules are confirmed.',
        ]);
    }
}

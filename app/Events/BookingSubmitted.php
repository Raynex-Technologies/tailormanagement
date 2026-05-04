<?php

namespace App\Events;

use App\Models\OnlineBooking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public OnlineBooking $booking) {}
}

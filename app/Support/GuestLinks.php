<?php

namespace App\Support;

use App\Models\Reservation;
use Illuminate\Support\Facades\URL;

/**
 * Signed links that let a guest open their booking or order from any
 * browser, such as the one in a confirmation email. One method per kind.
 */
class GuestLinks
{
    private const BOOKING_DAYS_AFTER = 7;

    public static function booking(Reservation $reservation): string
    {
        return URL::temporarySignedRoute(
            'book.show',
            $reservation->ends_at->addDays(self::BOOKING_DAYS_AFTER),
            ['reservation' => $reservation->reference],
        );
    }
}

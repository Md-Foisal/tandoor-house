<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Support\Facades\URL;

/**
 * Signed links that let a guest open their booking or order from any
 * browser, such as the one in a confirmation email. One method per kind.
 */
class GuestLinks
{
    private const BOOKING_DAYS_AFTER = 7;

    private const ORDER_DAYS_AFTER = 7;

    public static function booking(Reservation $reservation): string
    {
        return URL::temporarySignedRoute(
            'book.show',
            $reservation->ends_at->addDays(self::BOOKING_DAYS_AFTER),
            ['reservation' => $reservation->reference],
        );
    }

    /**
     * Valid for a week after the order, or after the message goes out if
     * that is later (a delivered notice can follow a long wait).
     */
    public static function order(Order $order): string
    {
        $from = $order->placed_at->isAfter(now()) ? $order->placed_at : now();

        return URL::temporarySignedRoute(
            'orders.show',
            $from->addDays(self::ORDER_DAYS_AFTER),
            ['order' => $order->number],
        );
    }
}

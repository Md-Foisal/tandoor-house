<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public const SESSION_KEY = 'guest_reservations';

    /**
     * The customer who booked, staff, or the guest who booked in this session.
     */
    public function view(?User $user, Reservation $reservation): bool
    {
        if ($user?->isStaff()) {
            return true;
        }

        if ($user !== null && $reservation->user_id === $user->id) {
            return true;
        }

        return in_array($reservation->reference, (array) session()->get(self::SESSION_KEY, []), true);
    }

    /**
     * Only whoever booked, not staff: they change bookings on the floor screens.
     */
    public function cancel(?User $user, Reservation $reservation): bool
    {
        if ($user !== null && $reservation->user_id === $user->id) {
            return true;
        }

        return in_array($reservation->reference, (array) session()->get(self::SESSION_KEY, []), true);
    }

    /**
     * Paying the deposit is for whoever booked.
     */
    public function pay(?User $user, Reservation $reservation): bool
    {
        return $this->cancel($user, $reservation);
    }
}

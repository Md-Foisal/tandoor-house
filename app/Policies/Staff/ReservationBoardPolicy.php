<?php

namespace App\Policies\Staff;

use App\Enums\StaffArea;
use App\Models\Reservation;
use App\Models\User;

/**
 * Staff rules for bookings on the reservation board and the floor, exposed as
 * "staff.reservations.*" gate abilities. ReservationPolicy covers the guest
 * or customer who made the booking.
 */
class ReservationBoardPolicy
{
    public function book(User $user): bool
    {
        return StaffArea::Reservations->allows($user->role);
    }

    /**
     * Confirming a deposit taken in person moves money, so it is for managers
     * and owners; anyone on the board may seat, complete, cancel or mark a no-show.
     */
    public function transition(User $user, Reservation $reservation, ?string $action = null): bool
    {
        if (! StaffArea::Reservations->allows($user->role)) {
            return false;
        }

        return $action !== 'confirm' || $user->role->isManagement();
    }

    /** Seating happens from the floor map as well as the board. */
    public function seat(User $user, Reservation $reservation): bool
    {
        return StaffArea::Floor->allows($user->role) || StaffArea::Reservations->allows($user->role);
    }

    public function reassignTable(User $user, Reservation $reservation): bool
    {
        return StaffArea::Reservations->allows($user->role);
    }
}

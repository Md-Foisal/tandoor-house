<?php

namespace App\Support;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\CarbonImmutable;

/**
 * How long an unpaid deposit booking keeps its table. Derived from when the
 * booking was made, so nothing extra is stored; the scheduler releases
 * bookings once `expired()` is true.
 */
class DepositHold
{
    public const DEFAULT_MINUTES = 15;

    public function __construct(private readonly Settings $settings) {}

    public function minutes(): int
    {
        return max(1, (int) $this->settings->get('reservation.hold_minutes', self::DEFAULT_MINUTES));
    }

    public function until(Reservation $reservation): CarbonImmutable
    {
        return CarbonImmutable::instance($reservation->created_at ?? now())->addMinutes($this->minutes());
    }

    public function expired(Reservation $reservation): bool
    {
        return $reservation->status === ReservationStatus::PendingDeposit && $this->until($reservation)->isPast();
    }
}

<?php

namespace App\Support;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\CarbonImmutable;

/**
 * How long an unpaid deposit booking keeps its table. Derived from when the
 * booking was made, so nothing extra is stored. Once `expired()` is true the
 * table counts as free again (TableAvailability), the deposit can no longer
 * be started or confirmed, and a payment that still lands is refunded. The
 * booking itself stays pending_deposit until something cancels it.
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

    /** Bookings made before this moment no longer hold their table unless paid. */
    public function cutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()->subMinutes($this->minutes());
    }

    public function expired(Reservation $reservation): bool
    {
        return $reservation->status === ReservationStatus::PendingDeposit && $this->until($reservation)->isPast();
    }
}

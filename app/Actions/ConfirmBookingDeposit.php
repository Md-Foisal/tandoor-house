<?php

namespace App\Actions;

use App\Enums\ReservationStatus;
use App\Events\ReservationUpdated;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

/**
 * Moves a booking whose deposit is paid from pending to confirmed. The
 * status is re-read under a lock, so the guest returning and the webhook
 * arriving together announce the booking a single time.
 */
class ConfirmBookingDeposit
{
    public function handle(Reservation $reservation): bool
    {
        $moved = DB::transaction(function () use ($reservation) {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($locked->status !== ReservationStatus::PendingDeposit) {
                return false;
            }

            $locked->forceFill(['status' => ReservationStatus::Confirmed])->save();

            return true;
        });

        if ($moved) {
            ReservationUpdated::dispatch($reservation->refresh());
        }

        return $moved;
    }
}

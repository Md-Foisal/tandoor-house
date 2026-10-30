<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Events\ReservationUpdated;
use App\Models\Reservation;
use App\Support\Automation;
use Illuminate\Support\Facades\DB;

/**
 * The automatic twin of a host marking a booking as a no-show. The deposit is
 * kept as it stands and only recorded in the log.
 */
class MarkNoShow
{
    public function __construct(private readonly Automation $automation) {}

    public function handle(Reservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation) {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->first();

            if ($reservation === null || $reservation->status !== ReservationStatus::Confirmed) {
                return false;
            }

            $reservation->update(['status' => ReservationStatus::NoShow]);
            $reservation->diningTable()->where('status', TableStatus::Reserved)->update(['status' => TableStatus::Free]);

            $this->automation->log(
                'automation.booking.no_show',
                __('automations.bookings.no_show', ['name' => $reservation->name, 'party' => $reservation->party_size]),
                $reservation,
                array_filter(['deposit' => $reservation->deposit, 'deposit_status' => $reservation->deposit_status?->value]),
            );

            ReservationUpdated::dispatch($reservation);

            return true;
        });
    }
}

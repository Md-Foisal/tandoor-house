<?php

namespace App\Actions\Floor;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Events\ReservationUpdated;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one path that seats a booking, whether it comes from the floor map or
 * the reservations board.
 */
class SeatReservation
{
    public function __construct(private readonly ActivityRecorder $log) {}

    public function handle(Reservation $reservation, ?int $tableId, User $by): Reservation
    {
        return DB::transaction(function () use ($reservation, $tableId, $by) {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->status === ReservationStatus::PendingDeposit) {
                $this->fail(__('staff_floor.errors.seat_deposit', ['name' => $reservation->name]));
            }

            if ($reservation->status !== ReservationStatus::Confirmed) {
                $this->fail(__('staff_floor.errors.seat_status', ['name' => $reservation->name]));
            }

            $id = $tableId ?? $reservation->dining_table_id;

            if ($id === null) {
                $this->fail(__('staff_floor.errors.seat_table_required', ['name' => $reservation->name]));
            }

            $table = DiningTable::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $heldForThem = $table->status === TableStatus::Reserved && $reservation->dining_table_id === $table->id;

            if ($table->merged_into_id !== null || ($table->status !== TableStatus::Free && ! $heldForThem)) {
                $this->fail(__('staff_floor.errors.seat_busy', ['table' => $table->label]));
            }

            $seats = TableLayout::groupSeats($table);

            if ($reservation->party_size > $seats) {
                $this->fail(__('staff_floor.errors.seat_small', ['table' => $table->label, 'seats' => $seats, 'size' => $reservation->party_size]));
            }

            $now = now();

            $reservation->forceFill([
                'status' => ReservationStatus::Seated,
                'seated_at' => $now,
                'dining_table_id' => $table->id,
            ])->save();

            $table->forceFill([
                'status' => TableStatus::Seated,
                'party_size' => $reservation->party_size,
                'seated_at' => $now,
            ])->save();
            DiningTable::query()->where('merged_into_id', $table->id)->update(['status' => TableStatus::Seated]);

            $this->log->record('reservation.seated', __('staff_floor.log.booking_seated', ['name' => $reservation->name, 'table' => $table->label]), $reservation, [
                'table' => $table->label,
                'party_size' => $reservation->party_size,
            ], $by);

            ReservationUpdated::dispatch($reservation);

            return $reservation;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['reservation' => $message]);
    }
}

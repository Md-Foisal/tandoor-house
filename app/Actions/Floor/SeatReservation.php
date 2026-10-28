<?php

namespace App\Actions\Floor;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatReservation
{
    public function __construct(private readonly RecordFloorActivity $log) {}

    public function handle(Reservation $reservation, ?int $tableId, User $by): Reservation
    {
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

        $table = DiningTable::query()->findOrFail($id);
        $heldForThem = $table->status === TableStatus::Reserved && $reservation->dining_table_id === $table->id;

        if ($table->merged_into_id !== null || ($table->status !== TableStatus::Free && ! $heldForThem)) {
            $this->fail(__('staff_floor.errors.seat_busy', ['table' => $table->label]));
        }

        $seats = TableLayout::groupSeats($table);

        if ($reservation->party_size > $seats) {
            $this->fail(__('staff_floor.errors.seat_small', ['table' => $table->label, 'seats' => $seats, 'size' => $reservation->party_size]));
        }

        return DB::transaction(function () use ($reservation, $table, $by) {
            $reservation->forceFill([
                'status' => ReservationStatus::Seated,
                'seated_at' => now(),
                'dining_table_id' => $table->id,
            ])->save();

            $table->forceFill(['status' => TableStatus::Seated])->save();
            DiningTable::query()->where('merged_into_id', $table->id)->update(['status' => TableStatus::Seated]);

            $this->log->handle('reservation.seated', $table, $by, __('staff_floor.log.booking_seated', ['name' => $reservation->name, 'table' => $table->label]), [
                'reservation' => $reservation->reference,
                'party_size' => $reservation->party_size,
            ]);

            return $reservation;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['reservation' => $message]);
    }
}

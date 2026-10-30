<?php

namespace App\Http\Controllers\Staff\Reservations;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Events\ReservationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reservations\ReassignTableRequest;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReassignTableController extends Controller
{
    public function __construct(private readonly ActivityRecorder $log) {}

    public function __invoke(ReassignTableRequest $request, Reservation $reservation): RedirectResponse
    {
        DB::transaction(function () use ($request, $reservation) {
            $reservation = Reservation::query()->with('diningTable')->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($reservation->status, BoardData::HOLDING, true)) {
                throw ValidationException::withMessages(['dining_table_id' => __('staff_reservations.errors.cannot_move')]);
            }

            $table = DiningTable::query()->whereNull('merged_into_id')->whereKey($request->integer('dining_table_id'))->lockForUpdate()->first();

            if ($table === null || $table->seats < $reservation->party_size) {
                throw ValidationException::withMessages(['dining_table_id' => __('staff_reservations.errors.table_too_small')]);
            }

            $seated = $reservation->status === ReservationStatus::Seated;

            if ($seated && $table->status !== TableStatus::Free) {
                throw ValidationException::withMessages(['dining_table_id' => __('staff_reservations.errors.table_busy', ['table' => $table->label])]);
            }

            $clash = Reservation::query()
                ->where('dining_table_id', $table->id)
                ->whereKeyNot($reservation->id)
                ->whereIn('status', BoardData::HOLDING)
                ->where('starts_at', '<', $reservation->ends_at)
                ->where('ends_at', '>', $reservation->starts_at)
                ->lockForUpdate()
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages(['dining_table_id' => __('staff_reservations.errors.table_taken', ['table' => $table->label])]);
            }

            $from = $reservation->diningTable;
            $reservation->update(['dining_table_id' => $table->id]);

            if ($seated) {
                $from?->forceFill(['status' => TableStatus::NeedsCleaning, 'party_size' => null, 'seated_at' => null])->save();
                $table->forceFill(['status' => TableStatus::Seated, 'party_size' => $reservation->party_size, 'seated_at' => $reservation->seated_at])->save();
            }

            ReservationUpdated::dispatch($reservation);

            $this->log->record('reservation.moved', __('staff_reservations.log.moved', ['name' => $reservation->name, 'from' => $from->label ?? '-', 'to' => $table->label]), $reservation, [], $request->user());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_reservations.flash.moved', ['name' => $reservation->name])]);

        return back();
    }
}

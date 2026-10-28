<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Actions\Floor\SeatReservation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Floor\SeatReservationRequest;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ReservationController extends Controller
{
    public function __invoke(SeatReservationRequest $request, Reservation $reservation, SeatReservation $seat): RedirectResponse
    {
        $seat->handle($reservation, $request->integer('table_id') ?: null, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_floor.toast.seated', ['name' => $reservation->name])]);

        return back();
    }
}

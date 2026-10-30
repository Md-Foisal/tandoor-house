<?php

namespace App\Http\Controllers\Staff\Reservations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reservations\TransitionReservationRequest;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TransitionController extends Controller
{
    public function __construct(private readonly ReservationActions $actions) {}

    public function __invoke(TransitionReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $reason = $request->filled('reason') ? $request->string('reason')->trim()->toString() : null;

        $this->actions->apply($reservation, $action, $request->user(), $reason);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_reservations.flash.'.$action, ['name' => $reservation->name])]);

        return back();
    }
}

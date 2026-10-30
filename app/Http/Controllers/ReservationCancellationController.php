<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelReservationRequest;
use App\Models\Reservation;
use App\Services\ReservationCancellation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ReservationCancellationController extends Controller
{
    public function store(CancelReservationRequest $request, Reservation $reservation, ReservationCancellation $cancellation): RedirectResponse
    {
        $outcome = $cancellation->cancel($reservation, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('booking.cancel.done_'.$outcome),
        ]);

        return redirect()->route('book.show', $reservation);
    }
}

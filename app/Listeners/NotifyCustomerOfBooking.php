<?php

namespace App\Listeners;

use App\Events\ReservationUpdated;
use App\Models\Reservation;
use App\Notifications\Customer\BookingNotice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class NotifyCustomerOfBooking implements ShouldQueue
{
    public function handle(ReservationUpdated $event): void
    {
        if (! BookingNotice::shouldNotify($event->status)) {
            return;
        }

        $reservation = Reservation::find($event->reservationId);

        // Tied to the status the event carried: a table swap fires the same
        // event again and must not resend the confirmation.
        if ($reservation === null || $reservation->status !== $event->status) {
            return;
        }

        if (! Cache::add("booking-notice:{$reservation->id}:{$event->status->value}", true, now()->addDays(30))) {
            return;
        }

        Notification::route('mail', $reservation->email)
            ->route('sms', $reservation->phone)
            ->route('whatsapp', $reservation->phone)
            ->notify(new BookingNotice($reservation, $event->status));
    }
}

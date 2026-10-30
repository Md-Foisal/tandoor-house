<?php

namespace App\Listeners;

use App\Actions\ConfirmBookingDeposit;
use App\Enums\PaymentStatus;
use App\Events\PaymentUpdated;
use App\Models\Reservation;

/**
 * A webhook can mark the deposit paid before the guest comes back to the
 * site; the table is theirs from that moment, not from their return.
 */
class ConfirmBookingOnDeposit
{
    public function __construct(private readonly ConfirmBookingDeposit $confirm) {}

    public function handle(PaymentUpdated $event): void
    {
        $payment = $event->payment;

        if ($payment->status !== PaymentStatus::Paid || $payment->gateway === 'venue' || $event->from === PaymentStatus::Paid) {
            return;
        }

        $reservation = $payment->payableModel();

        if ($reservation instanceof Reservation) {
            $this->confirm->handle($reservation);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\Customer\BookingReminder;
use App\Support\Automation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

#[Signature('bookings:remind')]
#[Description('Remind guests about a confirmed booking starting within two hours')]
class RemindBookings extends Command
{
    public const LEAD_MINUTES = 120;

    protected const CHUNK = 100;

    public function handle(Automation $automation): int
    {
        if (! $automation->enabled(Automation::RESERVATION_REMINDERS)) {
            return self::SUCCESS;
        }

        $sent = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->whereNull('reminded_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addMinutes(self::LEAD_MINUTES))
            ->lazyById(self::CHUNK)
            ->each(function (Reservation $reservation) use ($automation, &$sent) {
                // Someone who booked inside the window has only just been told; a reminder now is noise.
                if ($reservation->created_at !== null && $reservation->created_at->greaterThan($reservation->starts_at->copy()->subMinutes(self::LEAD_MINUTES))) {
                    return;
                }

                $claimed = DB::transaction(function () use ($reservation) {
                    $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->first();

                    if ($locked === null || $locked->reminded_at !== null || $locked->status !== ReservationStatus::Confirmed) {
                        return null;
                    }

                    $locked->update(['reminded_at' => now()]);

                    return $locked;
                });

                if ($claimed === null) {
                    return;
                }

                Notification::route('mail', $claimed->email)
                    ->route('sms', $claimed->phone)
                    ->route('whatsapp', $claimed->phone)
                    ->notify(new BookingReminder($claimed));

                $automation->log(
                    'automation.booking.reminded',
                    __('automations.bookings.reminded', ['name' => $claimed->name]),
                    $claimed,
                    ['starts_at' => $claimed->starts_at->toIso8601String()],
                );

                $sent++;
            });

        $this->info(trans_choice('automations.bookings.reminders', $sent, ['count' => $sent]));

        return self::SUCCESS;
    }
}

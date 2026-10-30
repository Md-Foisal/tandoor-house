<?php

namespace App\Console\Commands;

use App\Actions\Reservations\MarkNoShow;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\Automation;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:release-no-shows')]
#[Description('Mark confirmed bookings nobody has turned up for as no-shows and free their tables')]
class ReleaseNoShows extends Command
{
    public function handle(Automation $automation, Settings $settings, MarkNoShow $markNoShow): int
    {
        if (! $automation->enabled(Automation::NO_SHOW_RELEASE)) {
            return self::SUCCESS;
        }

        $grace = max(1, (int) $settings->get('reservation.no_show_minutes', 20));
        $released = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->where('starts_at', '<=', now()->subMinutes($grace))
            ->each(function (Reservation $reservation) use ($markNoShow, &$released) {
                if ($markNoShow->handle($reservation)) {
                    $released++;
                }
            });

        $this->info(trans_choice('automations.bookings.released', $released, ['count' => $released]));

        return self::SUCCESS;
    }
}

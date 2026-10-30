<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Events\ReservationUpdated;
use App\Models\Reservation;
use App\Payments\Payments;
use App\Support\Automation;
use App\Support\DepositHold;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('bookings:close-lapsed-holds')]
#[Description('Cancel bookings whose deposit was not paid before the hold ran out')]
class CloseLapsedHolds extends Command
{
    public function handle(Automation $automation, DepositHold $hold, Payments $payments): int
    {
        $closed = 0;

        Reservation::query()
            ->where('status', ReservationStatus::PendingDeposit)
            ->where('created_at', '<=', $hold->cutoff())
            ->each(function (Reservation $reservation) use ($automation, $hold, $payments, &$closed) {
                $done = DB::transaction(function () use ($reservation, $automation, $hold) {
                    $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->first();

                    if ($locked === null || ! $hold->expired($locked)) {
                        return null;
                    }

                    $locked->update(['status' => ReservationStatus::Cancelled]);
                    $locked->diningTable()->where('status', TableStatus::Reserved)->update(['status' => TableStatus::Free]);

                    $automation->log(
                        'automation.booking.hold_lapsed',
                        __('automations.bookings.hold_lapsed', ['name' => $locked->name]),
                        $locked,
                        ['reason' => 'deposit not paid', 'deposit' => $locked->deposit],
                    );

                    ReservationUpdated::dispatch($locked);

                    return $locked;
                });

                if ($done !== null) {
                    $payments->cancelOpen($done);
                    $closed++;
                }
            });

        $this->info(trans_choice('automations.bookings.closed', $closed, ['count' => $closed]));

        return self::SUCCESS;
    }
}

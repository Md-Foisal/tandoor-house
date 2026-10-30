<?php

namespace App\Http\Controllers\Staff\Reservations;

use App\Actions\Floor\SeatReservation;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Events\ReservationUpdated;
use App\Models\Reservation;
use App\Models\User;
use App\Support\ActivityRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Status changes for a booking and the table beside it. Every change that
 * matters writes a line to the activity log. Each runs on the booking row
 * locked inside the transaction, so a double tap cannot apply it twice.
 */
class ReservationActions
{
    public function __construct(
        private readonly ActivityRecorder $log,
        private readonly SeatReservation $seatReservation,
    ) {}

    public function apply(Reservation $reservation, string $action, User $user, ?string $reason = null): void
    {
        DB::transaction(function () use ($reservation, $action, $user, $reason) {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            match ($action) {
                'confirm' => $this->confirm($reservation, $user, (string) $reason),
                'seat' => $this->seat($reservation, $user),
                'complete' => $this->complete($reservation, $user),
                'no_show' => $this->noShow($reservation, $user, $reason),
                'cancel' => $this->cancel($reservation, $user, (string) $reason),
                default => throw new InvalidArgumentException("Unknown reservation action [{$action}]."),
            };

            // SeatReservation announces its own change.
            if ($action !== 'seat') {
                ReservationUpdated::dispatch($reservation);
            }
        });
    }

    /**
     * Taking a deposit in person moves money, so it stays with management and
     * leaves a note or payment reference behind.
     */
    private function confirm(Reservation $reservation, User $user, string $reference): void
    {
        if (! $user->role->isManagement()) {
            abort(403);
        }

        if (trim($reference) === '') {
            throw ValidationException::withMessages(['reason' => __('staff_reservations.errors.reference_required')]);
        }

        $this->require($reservation, [ReservationStatus::PendingDeposit]);

        $reservation->update(['status' => ReservationStatus::Confirmed, 'deposit_status' => PaymentStatus::Paid]);
        $this->log->record('reservation.deposit_taken', __('staff_reservations.log.confirmed', ['name' => $reservation->name]), $reservation, ['deposit' => $reservation->deposit, 'reference' => trim($reference)], $user);
    }

    private function seat(Reservation $reservation, User $user): void
    {
        try {
            $this->seatReservation->handle($reservation, null, $user);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['action' => $e->errors()['reservation'] ?? []]);
        }
    }

    private function complete(Reservation $reservation, User $user): void
    {
        $this->require($reservation, [ReservationStatus::Seated]);

        $reservation->update(['status' => ReservationStatus::Completed]);
        $reservation->diningTable()->whereIn('status', [TableStatus::Seated, TableStatus::BillRequested])->update(['status' => TableStatus::NeedsCleaning, 'party_size' => null, 'seated_at' => null]);
        $this->log->record('reservation.completed', __('staff_reservations.log.completed', ['name' => $reservation->name]), $reservation, [], $user);
    }

    private function noShow(Reservation $reservation, User $user, ?string $reason): void
    {
        $this->require($reservation, [ReservationStatus::Confirmed, ReservationStatus::PendingDeposit]);

        if ($reservation->starts_at->isFuture()) {
            throw ValidationException::withMessages(['action' => __('staff_reservations.errors.too_early', ['time' => CarbonImmutable::instance($reservation->starts_at)->setTimezone(config('app.timezone'))->format('H:i')])]);
        }

        $reservation->update(['status' => ReservationStatus::NoShow]);
        $this->releaseTable($reservation);
        $this->log->record('reservation.no_show', __('staff_reservations.log.no_show', ['name' => $reservation->name, 'party' => $reservation->party_size]), $reservation, array_filter(['reason' => $reason, 'deposit' => $reservation->deposit, 'deposit_status' => $reservation->deposit_status?->value]), $user);
    }

    private function cancel(Reservation $reservation, User $user, string $reason): void
    {
        $this->require($reservation, [ReservationStatus::Confirmed, ReservationStatus::PendingDeposit]);

        $reservation->update(['status' => ReservationStatus::Cancelled]);
        $this->releaseTable($reservation);
        $this->log->record('reservation.cancelled', __('staff_reservations.log.cancelled', ['name' => $reservation->name, 'reason' => $reason]), $reservation, array_filter(['reason' => $reason, 'deposit' => $reservation->deposit, 'deposit_status' => $reservation->deposit_status?->value]), $user);
    }

    private function releaseTable(Reservation $reservation): void
    {
        $reservation->diningTable()->where('status', TableStatus::Reserved)->update(['status' => TableStatus::Free]);
    }

    /**
     * @param  list<ReservationStatus>  $allowed
     */
    private function require(Reservation $reservation, array $allowed): void
    {
        if (! in_array($reservation->status, $allowed, true)) {
            throw ValidationException::withMessages(['action' => __('staff_reservations.errors.wrong_state', ['status' => __('staff_reservations.status.'.$reservation->status->value)])]);
        }
    }
}

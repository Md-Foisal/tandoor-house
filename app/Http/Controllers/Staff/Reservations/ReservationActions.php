<?php

namespace App\Http\Controllers\Staff\Reservations;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Status changes for a booking and the table beside it. Every change that
 * matters writes a line to the activity log.
 */
class ReservationActions
{
    public function apply(Reservation $reservation, string $action, User $user, ?string $reason = null): void
    {
        DB::transaction(function () use ($reservation, $action, $user, $reason) {
            match ($action) {
                'confirm' => $this->confirm($reservation, $user),
                'seat' => $this->seat($reservation, $user),
                'complete' => $this->complete($reservation, $user),
                'no_show' => $this->noShow($reservation, $user, $reason),
                'cancel' => $this->cancel($reservation, $user, (string) $reason),
                default => throw new InvalidArgumentException("Unknown reservation action [{$action}]."),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, Reservation $reservation, ?User $user, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'event' => $event,
            'subject_type' => $reservation->getMorphClass(),
            'subject_id' => $reservation->id,
            'user_id' => $user?->id,
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
        ]);
    }

    private function confirm(Reservation $reservation, User $user): void
    {
        $this->require($reservation, [ReservationStatus::PendingDeposit]);

        $reservation->update(['status' => ReservationStatus::Confirmed, 'deposit_status' => PaymentStatus::Paid]);
        $this->log('reservation.deposit_taken', $reservation, $user, __('staff_reservations.log.confirmed', ['name' => $reservation->name]), ['deposit' => $reservation->deposit]);
    }

    private function seat(Reservation $reservation, User $user): void
    {
        $this->require($reservation, [ReservationStatus::Confirmed]);

        if ($reservation->dining_table_id === null) {
            throw ValidationException::withMessages(['action' => __('staff_reservations.errors.no_table')]);
        }

        $reservation->update(['status' => ReservationStatus::Seated, 'seated_at' => now()]);
        $reservation->diningTable()->update(['status' => TableStatus::Seated]);
        $this->log('reservation.seated', $reservation, $user, __('staff_reservations.log.seated', ['name' => $reservation->name]));
    }

    private function complete(Reservation $reservation, User $user): void
    {
        $this->require($reservation, [ReservationStatus::Seated]);

        $reservation->update(['status' => ReservationStatus::Completed]);
        $reservation->diningTable()->where('status', TableStatus::Seated)->update(['status' => TableStatus::NeedsCleaning]);
        $this->log('reservation.completed', $reservation, $user, __('staff_reservations.log.completed', ['name' => $reservation->name]));
    }

    private function noShow(Reservation $reservation, User $user, ?string $reason): void
    {
        $this->require($reservation, [ReservationStatus::Confirmed, ReservationStatus::PendingDeposit]);

        if ($reservation->starts_at->isFuture()) {
            throw ValidationException::withMessages(['action' => __('staff_reservations.errors.too_early', ['time' => CarbonImmutable::instance($reservation->starts_at)->setTimezone(config('app.timezone'))->format('H:i')])]);
        }

        $reservation->update(['status' => ReservationStatus::NoShow]);
        $this->releaseTable($reservation);
        $this->log('reservation.no_show', $reservation, $user, __('staff_reservations.log.no_show', ['name' => $reservation->name, 'party' => $reservation->party_size]), array_filter(['reason' => $reason, 'deposit' => $reservation->deposit, 'deposit_status' => $reservation->deposit_status?->value]));
    }

    private function cancel(Reservation $reservation, User $user, string $reason): void
    {
        $this->require($reservation, [ReservationStatus::Confirmed, ReservationStatus::PendingDeposit]);

        $reservation->update(['status' => ReservationStatus::Cancelled]);
        $this->releaseTable($reservation);
        $this->log('reservation.cancelled', $reservation, $user, __('staff_reservations.log.cancelled', ['name' => $reservation->name, 'reason' => $reason]), array_filter(['reason' => $reason, 'deposit' => $reservation->deposit, 'deposit_status' => $reservation->deposit_status?->value]));
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

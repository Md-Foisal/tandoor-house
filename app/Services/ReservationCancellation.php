<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Events\ReservationUpdated;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Payments;
use App\Support\ActivityRecorder;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A customer cancelling their own booking. Up to the cut-off the deposit
 * goes back in full; inside it the table is still freed but the deposit is
 * kept. Either way the decision is written to the activity log.
 */
class ReservationCancellation
{
    private const DEFAULT_FREE_HOURS = 24;

    private const CANCELLABLE = [ReservationStatus::PendingDeposit, ReservationStatus::Confirmed];

    public function __construct(
        private readonly Settings $settings,
        private readonly Payments $payments,
        private readonly ActivityRecorder $activity,
    ) {}

    public function freeHours(): int
    {
        return max(0, (int) $this->settings->get('reservation.free_cancel_hours', self::DEFAULT_FREE_HOURS));
    }

    /**
     * What the customer is told before they confirm.
     *
     * @return array{allowed: bool, free: bool, hours: int, deadline_label: string, deposit_paid: int}
     */
    public function terms(Reservation $reservation, bool $mayCancel): array
    {
        $deadline = $this->deadline($reservation);

        return [
            'allowed' => $mayCancel && $this->isCancellable($reservation),
            'free' => $this->isFree($reservation),
            'hours' => $this->freeHours(),
            'deadline_label' => $deadline->timezone(config('app.timezone'))->translatedFormat('l j F \a\t H:i'),
            'deposit_paid' => $this->heldDeposit($reservation),
        ];
    }

    /**
     * @return 'refunded'|'kept'|'none' what happened to the deposit
     */
    public function cancel(Reservation $reservation, ?User $by): string
    {
        $outcome = DB::transaction(function () use ($reservation, $by) {
            $locked = Reservation::query()->lockForUpdate()->whereKey($reservation->getKey())->firstOrFail();

            if (! $this->isCancellable($locked)) {
                throw ValidationException::withMessages(['cancel' => __('booking.cancel.too_late')]);
            }

            $free = $this->isFree($locked);
            $held = $this->heldDeposit($locked);
            $outcome = $held < 1 ? 'none' : ($free ? 'refunded' : 'kept');

            if ($outcome === 'refunded') {
                $this->refundDeposit($locked, $by);
                $locked->refresh();
            }

            $locked->forceFill(['status' => ReservationStatus::Cancelled])->save();

            $this->activity->record(
                'reservation.cancelled_by_customer',
                __('booking.cancel.log', [
                    'reference' => $locked->reference,
                    'deposit' => __('booking.cancel.log_deposit_'.$outcome),
                ]),
                $locked,
                ['free_cancellation' => $free, 'deposit' => $held, 'outcome' => $outcome],
                $by,
            );

            $reservation->setRawAttributes($locked->getAttributes(), true);

            return $outcome;
        });

        // After the commit, so a deposit that lands meanwhile sees the booking
        // cancelled and is refunded rather than kept.
        $this->payments->cancelOpen($reservation);

        ReservationUpdated::dispatch($reservation);

        return $outcome;
    }

    private function isCancellable(Reservation $reservation): bool
    {
        return in_array($reservation->status, self::CANCELLABLE, true) && $reservation->starts_at->isFuture();
    }

    private function isFree(Reservation $reservation): bool
    {
        return $this->deadline($reservation)->isFuture();
    }

    private function deadline(Reservation $reservation): CarbonImmutable
    {
        return CarbonImmutable::instance($reservation->starts_at)->subHours($this->freeHours());
    }

    /**
     * What is still held of the deposit after any refunds already made. A
     * deposit marked paid with no payment recorded (taken outside the site,
     * or demo data) counts in full.
     */
    private function heldDeposit(Reservation $reservation): int
    {
        if (! in_array($reservation->deposit_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
            return 0;
        }

        $paid = $reservation->payments()
            ->whereIn('status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded])
            ->get();

        return $paid->isEmpty()
            ? $reservation->deposit
            : (int) $paid->sum(fn (Payment $payment) => $payment->amount - $payment->refunded);
    }

    private function refundDeposit(Reservation $reservation, ?User $by): void
    {
        $payments = $reservation->payments()
            ->whereIn('status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded])
            ->with('payable')
            ->get();

        // Payments::refund only needs a name for the receipt; guests have no account.
        $actor = $by ?? (new User)->forceFill(['name' => __('booking.cancel.guest_actor')]);

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            $left = $payment->amount - $payment->refunded;

            if ($left < 1) {
                continue;
            }

            try {
                $this->payments->refund($payment, $left, __('booking.cancel.refund_reason'), $actor);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['cancel' => (string) collect($e->errors())->flatten()->first()]);
            } catch (PaymentException|ConnectionException) {
                throw ValidationException::withMessages(['cancel' => __('booking.cancel.refund_failed')]);
            }
        }
    }
}

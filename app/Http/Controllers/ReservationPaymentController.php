<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Events\ReservationUpdated;
use App\Http\Requests\Checkout\ConfirmPaymentRequest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Payments\AttemptStatus;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentAttempt;
use App\Payments\PaymentPageProps;
use App\Payments\Payments;
use App\Payments\StartedPayment;
use App\Support\DepositHold;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pays the deposit that holds a table. Only whoever booked (signed in, or
 * the same guest session) can pay it; the amount is the reservation's.
 */
class ReservationPaymentController extends Controller
{
    private const OPEN = [PaymentStatus::Pending, PaymentStatus::Failed];

    public function __construct(private readonly Payments $payments, private readonly DepositHold $hold) {}

    public function show(Request $request, Reservation $reservation): Response|RedirectResponse
    {
        Gate::authorize('pay', $reservation);

        $returned = $request->query('payment_intent');

        if (is_string($returned) && $returned !== '') {
            $this->settleReturn($reservation, $returned);
            $reservation->refresh();
        }

        if ($this->isDone($reservation)) {
            return redirect()->route('book.show', $reservation);
        }

        $start = CarbonImmutable::instance($reservation->starts_at)->setTimezone(config('app.timezone'));
        $summary = [
            'reference' => $reservation->reference,
            'party_size' => $reservation->party_size,
            'date_label' => $start->translatedFormat('l j F'),
            'time' => $start->format('H:i'),
            'deposit' => $reservation->amountOwed(),
        ];
        $expires = $this->hold->until($reservation);
        $urls = ['booking' => route('book.show', $reservation), 'new' => route('book.create')];

        if ($this->hold->expired($reservation)) {
            return Inertia::render('book/pay', [
                'reservation' => $summary,
                'payment' => null,
                'declined' => null,
                'hold' => ['expires_at' => $expires->toIso8601String(), 'expired' => true],
                'urls' => $urls,
            ]);
        }

        try {
            $started = $this->current($reservation);
        } catch (PaymentException $exception) {
            return redirect()->route('book.show', $reservation)->with('error', $exception->getMessage());
        }

        return Inertia::render('book/pay', [
            'reservation' => $summary,
            'payment' => PaymentPageProps::for(
                $started,
                route('book.pay.confirm', $reservation),
                route('book.pay', $reservation),
            ),
            'declined' => $reservation->deposit_status === PaymentStatus::Failed ? __('payments.declined') : null,
            'hold' => ['expires_at' => $expires->toIso8601String(), 'expired' => false],
            'urls' => $urls,
        ]);
    }

    public function confirm(ConfirmPaymentRequest $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('pay', $reservation);

        $payment = $reservation->payments()->where('reference', $request->string('reference')->toString())->firstOrFail();

        if ($payment->gateway === 'fake' && $request->string('token')->toString() === 'tok_decline'
            && $payment->status === PaymentStatus::Pending) {
            return $this->answer($reservation, 'failed', __('payments.fake.declined'));
        }

        $payment = $this->payments->confirm((string) $payment->reference);
        $outcome = $this->outcome($payment);

        if ($outcome === 'paid') {
            $this->confirmReservation($reservation);
        }

        return $this->answer($reservation, $outcome, $payment->meta['failure'] ?? null);
    }

    /**
     * Moves a paid booking to confirmed once. The status is re-read under a
     * lock so two confirmations at once announce it a single time.
     */
    private function confirmReservation(Reservation $reservation): void
    {
        $moved = DB::transaction(function () use ($reservation) {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($locked->status !== ReservationStatus::PendingDeposit) {
                return false;
            }

            $locked->forceFill(['status' => ReservationStatus::Confirmed])->save();

            return true;
        });

        if ($moved) {
            ReservationUpdated::dispatch($reservation->refresh());
        }
    }

    private function isDone(Reservation $reservation): bool
    {
        return $reservation->status !== ReservationStatus::PendingDeposit
            || $reservation->deposit < 1
            || ! in_array($reservation->deposit_status, self::OPEN, true);
    }

    /**
     * Reuses the waiting payment so reloading does not open a new charge; a
     * declined or missing one starts a fresh attempt.
     */
    private function current(Reservation $reservation): StartedPayment
    {
        $pending = $reservation->payments()
            ->where('gateway', '!=', 'venue')
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($pending !== null) {
            $secret = $pending->gateway === 'fake'
                ? $pending->reference.'_secret_test'
                : $this->payments->gateway($pending->gateway)->retrieve($pending)->clientSecret;

            return new StartedPayment($pending, new PaymentAttempt(AttemptStatus::RequiresAction, (string) $pending->reference, $secret));
        }

        return $this->payments->start($reservation, PaymentMethod::Card, ['email' => $reservation->email]);
    }

    private function settleReturn(Reservation $reservation, string $reference): void
    {
        $payment = $reservation->payments()->where('reference', $reference)->first();

        if ($payment === null) {
            return;
        }

        $settled = $this->payments->confirm((string) $payment->reference);

        if ($this->outcome($settled) === 'paid') {
            $this->confirmReservation($reservation);
        }
    }

    private function outcome(Payment $payment): string
    {
        return match ($payment->status) {
            PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded => 'paid',
            PaymentStatus::Failed => 'failed',
            default => 'pending',
        };
    }

    private function answer(Reservation $reservation, string $status, ?string $message): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'message' => $status === 'failed' ? ($message ?: __('payments.declined')) : null,
            'redirect' => $status === 'paid' ? route('book.show', $reservation) : null,
        ]);
    }
}

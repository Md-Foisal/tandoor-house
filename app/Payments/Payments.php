<?php

namespace App\Payments;

use App\Actions\TableBill;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Events\PaymentUpdated;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TableBillPayment;
use App\Models\User;
use App\Payments\Exceptions\GatewayNotConfigured;
use App\Payments\Exceptions\PaymentException;
use App\Support\ActivityRecorder;
use App\Support\DepositHold;
use App\Support\Money;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The one way money changes state. Gateways talk to providers; this records
 * `payments` rows and moves the payable along.
 *
 * Rows are keyed on gateway + reference so a repeated start, confirm or
 * webhook finds the same row. Every change locks the payable, then the
 * payment (the same order RefundOrder uses), and re-reads both before
 * deciding, so two confirmations at once mark a payable paid only once.
 *
 * Only one attempt per payable is ever left open: starting a new one cancels
 * the others at the gateway. Money that lands anyway (a tab left open, a
 * cancel that lost the race) for something already paid, cancelled or
 * expired is sent straight back, as is a table share beyond what the table
 * still owed.
 */
class Payments
{
    private const SETTLED = [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded];

    public function __construct(
        private readonly Container $container,
        private readonly ActivityRecorder $activity,
        private readonly DepositHold $hold,
    ) {}

    public function gateway(string $name): PaymentGateway
    {
        $class = config("payments.drivers.{$name}");

        if (! is_string($class) || ! is_subclass_of($class, PaymentGateway::class)) {
            throw new GatewayNotConfigured("Unknown payment driver [{$name}].");
        }

        /** @var PaymentGateway */
        return $this->container->make($class);
    }

    public function for(PaymentMethod $method): PaymentGateway
    {
        return $this->gateway((string) config("payments.methods.{$method->value}"));
    }

    /**
     * Starts paying what the payable owes now; the amount is never taken
     * from the browser.
     *
     * @param  array<string, mixed>  $context  email, confirm (fake gateway only)
     */
    public function start(Payable&Model $for, PaymentMethod $method, array $context = []): StartedPayment
    {
        $gateway = $this->for($method);

        $this->cancelOpen($for);

        [$amount, $attempt] = DB::transaction(function () use ($for) {
            $payable = $this->lock($for);

            if (in_array($payable->paymentStatus(), self::SETTLED, true)) {
                throw new PaymentException(__('payments.already_paid'));
            }

            return [$payable->amountOwed(), $payable->payments()->count() + 1];
        });

        if ($amount < 1) {
            throw new PaymentException(__('payments.nothing_owed'));
        }

        $result = $gateway->start($for, $amount, $method, $context + [
            'attempt' => $attempt,
            'idempotency_key' => 'pay-'.$for->getMorphClass().'-'.$for->getKey().'-'.$attempt,
        ]);

        // The unique gateway + reference index makes this safe when two
        // requests race: firstOrCreate catches the duplicate insert and re-reads.
        $payment = Payment::query()->firstOrCreate(
            ['gateway' => $gateway->name(), 'reference' => $result->reference],
            [
                'order_id' => $for instanceof Order ? $for->getKey() : null,
                'payable_type' => $for->getMorphClass(),
                'payable_id' => $for->getKey(),
                'method' => $method,
                'amount' => $amount,
                'refunded' => 0,
                'status' => PaymentStatus::Pending,
            ],
        );

        if ($result->succeeded() || $result->failed()) {
            $this->settle($payment, $result->status, $result->message);
        }

        $for->refresh();

        return new StartedPayment($payment->refresh(), $result);
    }

    /**
     * Cancels every attempt on the payable that could still be completed:
     * waiting ones, and declined ones (a declined card intent can be retried
     * with another card from the old tab). Rows are marked failed and
     * cancelled; a gateway that cannot be reached is logged, and a payment
     * that lands regardless is refunded by settle().
     *
     * @return int attempts cancelled
     */
    public function cancelOpen(Payable&Model $payable, ?Payment $except = null): int
    {
        $open = $payable->payments()
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Failed])
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except?->getKey()))
            ->get()
            ->reject(fn (Payment $payment) => $this->isCancelled($payment));

        $cancelled = 0;

        /** @var Payment $payment */
        foreach ($open as $payment) {
            try {
                $this->gateway($payment->gateway)->cancel($payment);
            } catch (PaymentException|ConnectionException|GatewayNotConfigured $exception) {
                Log::warning('Could not cancel a payment at the gateway.', ['payment' => $payment->id, 'error' => $exception->getMessage()]);
            }

            $from = DB::transaction(function () use ($payment) {
                [, $locked] = $this->lockBoth($payment);

                if (! in_array($locked->status, [PaymentStatus::Pending, PaymentStatus::Failed], true) || $this->isCancelled($locked)) {
                    return null;
                }

                $from = $locked->status;
                $locked->status = PaymentStatus::Failed;
                $locked->meta = array_merge($locked->meta ?? [], [
                    'cancelled' => true,
                    'cancelled_at' => now()->toIso8601String(),
                    'failure' => $locked->meta['failure'] ?? __('payments.cancelled'),
                ]);
                $locked->save();

                return $from;
            });

            if ($from === null) {
                continue;
            }

            $cancelled++;

            if ($from === PaymentStatus::Pending) {
                PaymentUpdated::dispatch($payment->refresh(), $from);
            }
        }

        return $cancelled;
    }

    /**
     * Asks the gateway where a payment stands and records it: used when the
     * customer comes back from a redirect or the test form.
     */
    public function confirm(string $reference): Payment
    {
        $payment = Payment::query()->where('reference', $reference)->firstOrFail();
        $result = $this->gateway($payment->gateway)->retrieve($payment);

        if ($result->succeeded() || $result->failed()) {
            $this->settle($payment, $result->status, $result->message);
        }

        return $payment->refresh();
    }

    /**
     * Records a verified webhook. A repeat, or news about a payment we never
     * started, changes nothing.
     */
    public function applyWebhook(PaymentGateway $gateway, WebhookOutcome $outcome): bool
    {
        $payment = Payment::query()
            ->where('gateway', $gateway->name())
            ->where('reference', $outcome->reference)
            ->first();

        if ($payment === null || $outcome->status === AttemptStatus::RequiresAction || $outcome->status === AttemptStatus::Pending) {
            return false;
        }

        return $this->settle($payment, $outcome->status, $outcome->message, $outcome->amount);
    }

    /**
     * Staff took the money for a pay-at-venue or cash payment. Taking it
     * twice is harmless.
     */
    public function takeAtVenue(Payment $payment, User $by): Payment
    {
        if ($payment->gateway !== 'venue') {
            throw ValidationException::withMessages(['payment' => __('payments.venue.not_venue')]);
        }

        $from = DB::transaction(function () use ($payment, $by) {
            [$payable, $locked] = $this->lockBoth($payment);

            if (in_array($locked->status, self::SETTLED, true)) {
                return null;
            }

            if ($locked->status !== PaymentStatus::Pending) {
                throw ValidationException::withMessages(['payment' => __('payments.venue.not_pending')]);
            }

            if ($payable !== null && in_array($payable->paymentStatus(), self::SETTLED, true)) {
                throw ValidationException::withMessages(['payment' => __('payments.venue.already_paid')]);
            }

            $from = $locked->status;
            $locked->status = PaymentStatus::Paid;
            $locked->meta = array_merge($locked->meta ?? [], [
                'taken_by' => $by->name,
                'taken_by_id' => $by->id,
                'taken_at' => now()->toIso8601String(),
            ]);
            $locked->save();

            $payable?->markPaid();

            return $from;
        });

        $payment->refresh();

        if ($from !== null) {
            PaymentUpdated::dispatch($payment, $from);
        }

        return $payment;
    }

    /**
     * Sends money back through the payment's own gateway (cash for venue
     * payments) and records it on the original row, as RefundOrder does.
     * The gateway is called while the row is locked so two refunds at once
     * cannot together exceed what was paid.
     *
     * @param  array<int, int>  $lines  order line id => quantity, kept in the history
     */
    public function refund(Payment $payment, int $amount, string $reason, User $by, array $lines = []): Payment
    {
        $from = DB::transaction(function () use ($payment, $amount, $reason, $by, $lines) {
            [$payable, $locked] = $this->lockBoth($payment);

            if (! in_array($locked->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                throw ValidationException::withMessages(['amount' => __('payments.refund.nothing_paid')]);
            }

            $remaining = $locked->amount - $locked->refunded;

            if ($amount < 1 || $amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => __('payments.refund.too_much', ['max' => Money::format($remaining)]),
                ]);
            }

            $result = $this->gateway($locked->gateway)->refund($locked, $amount, $reason);

            if (! $result->succeeded) {
                throw ValidationException::withMessages(['amount' => $result->message ?? __('payments.refund.gateway_failed')]);
            }

            $from = $locked->status;
            $meta = $locked->meta ?? [];
            $meta['refunds'][] = [
                'amount' => $amount,
                'reason' => $reason,
                'by' => $by->name,
                'at' => now()->toIso8601String(),
                'lines' => $lines,
                'reference' => $result->reference,
                'via' => $result->via,
            ];

            $locked->refunded += $amount;
            $locked->status = $locked->refunded >= $locked->amount ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
            $locked->meta = $meta;
            $locked->save();

            if ($payable !== null) {
                $settled = $payable->payments()->whereIn('status', self::SETTLED)->get();
                $payable->markRefunded((int) $settled->sum('refunded') >= (int) $settled->sum('amount'));
            }

            return $from;
        });

        $payment->refresh();
        PaymentUpdated::dispatch($payment, $from);

        return $payment;
    }

    private function settle(Payment $payment, AttemptStatus $status, ?string $message, ?int $amount = null): bool
    {
        $result = DB::transaction(function () use ($payment, $status, $message, $amount) {
            [$payable, $locked] = $this->lockBoth($payment);
            $target = $status->paymentStatus();

            if (in_array($locked->status, self::SETTLED, true) || $locked->status === $target) {
                return null;
            }

            if ($amount !== null && $amount !== $locked->amount) {
                Log::warning('Payment amount from the gateway does not match the recorded amount.', [
                    'payment' => $locked->id,
                    'expected' => $locked->amount,
                    'received' => $amount,
                ]);

                return null;
            }

            $from = $locked->status;
            $refusal = $target === PaymentStatus::Paid && $payable !== null ? $this->refusal($payable) : null;
            $locked->status = $target;

            if ($status === AttemptStatus::Failed) {
                $locked->meta = array_merge($locked->meta ?? [], ['failure' => $message ?? __('payments.declined')]);
            }

            $locked->save();

            if ($payable === null) {
                return [$from, false];
            }

            if ($refusal !== null) {
                $this->autoRefund($payable, $locked, $locked->amount, $refusal);

                return [$from, false];
            }

            if ($target === PaymentStatus::Paid) {
                $payable->markPaid();

                if ($payable instanceof TableBillPayment) {
                    $this->refundExcess($payable, $locked);
                }

                return [$from, true];
            }

            if ($payable->paymentStatus() !== PaymentStatus::Paid && ! $this->isCancelled($locked) && ! $this->superseded($payable, $locked)) {
                $payable->markPaymentFailed();
            }

            return [$from, false];
        });

        if ($result === null) {
            return false;
        }

        [$from, $accepted] = $result;
        $payment->refresh();
        PaymentUpdated::dispatch($payment, $from);

        $payable = $payment->payableModel();

        if ($accepted && $payable !== null) {
            $this->cancelOpen($payable, $payment);
        }

        return true;
    }

    /**
     * Why money that just landed must go back, or null to keep it.
     *
     * @return 'duplicate'|'cancelled'|'expired'|null
     */
    private function refusal(Payable&Model $payable): ?string
    {
        if (in_array($payable->paymentStatus(), self::SETTLED, true)) {
            return 'duplicate';
        }

        if ($payable instanceof Order && in_array($payable->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
            return 'cancelled';
        }

        if ($payable instanceof Reservation) {
            if ($this->hold->expired($payable)) {
                return 'expired';
            }

            if (! in_array($payable->status, [ReservationStatus::PendingDeposit, ReservationStatus::Confirmed, ReservationStatus::Seated], true)) {
                return 'cancelled';
            }
        }

        return null;
    }

    /**
     * A table share that landed after others had already covered the bill
     * keeps only what it settled. The part of the bill left over is turned
     * back into money (the waived service charge was never charged), and the
     * tip goes back too when nothing at all was settled.
     */
    private function refundExcess(TableBillPayment $share, Payment $locked): void
    {
        $settles = $share->amount + $share->waived;
        $excess = $this->container->make(TableBill::class)->unallocated($share);

        if ($excess < 1) {
            return;
        }

        $money = $excess >= $settles
            ? $locked->amount
            : min($locked->amount, intdiv($excess * $share->amount + $settles - 1, $settles));

        if ($money > 0 && $this->autoRefund($share, $locked, $money, 'overpaid')) {
            $share->markRefunded($money >= $locked->amount);
        }
    }

    /**
     * Sends money back without anyone asking, while the payment is locked.
     * A refund the gateway refuses is logged for staff to finish by hand.
     *
     * @param  'duplicate'|'cancelled'|'expired'|'overpaid'  $why
     */
    private function autoRefund(Payable&Model $payable, Payment $locked, int $amount, string $why): bool
    {
        $reason = (string) __("payments.auto_refund.reason.{$why}");
        $context = [
            'amount' => Money::format($amount),
            'reference' => $payable->paymentReference(),
            'reason' => $reason,
        ];

        try {
            $result = $this->gateway($locked->gateway)->refund($locked, $amount, $reason);
        } catch (PaymentException|ConnectionException|GatewayNotConfigured $exception) {
            $result = new RefundResult(false, message: $exception->getMessage());
        }

        if (! $result->succeeded) {
            Log::error('An automatic refund failed and must be made by hand.', ['payment' => $locked->id, 'amount' => $amount, 'why' => $why, 'error' => $result->message]);

            $locked->meta = array_merge($locked->meta ?? [], ['auto_refund_failed' => ['amount' => $amount, 'why' => $why, 'error' => $result->message]]);
            $locked->save();

            $this->activity->record('payment.auto_refund_failed', __('payments.auto_refund.log_failed', $context), $payable,
                ['payment' => $locked->id, 'amount' => $amount, 'why' => $why], automated: true);

            return false;
        }

        $meta = $locked->meta ?? [];
        $meta['auto_refund'] = $why;
        $meta['refunds'][] = [
            'amount' => $amount,
            'reason' => $reason,
            'by' => __('payments.auto_refund.actor'),
            'at' => now()->toIso8601String(),
            'lines' => [],
            'reference' => $result->reference,
            'via' => $result->via,
            'automated' => true,
        ];

        $locked->refunded += $amount;
        $locked->status = $locked->refunded >= $locked->amount ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
        $locked->meta = $meta;
        $locked->save();

        $this->activity->record('payment.auto_refunded', __('payments.auto_refund.log', $context), $payable,
            ['payment' => $locked->id, 'amount' => $amount, 'why' => $why], automated: true);

        return true;
    }

    private function isCancelled(Payment $payment): bool
    {
        return ($payment->meta['cancelled'] ?? false) === true;
    }

    /** A newer attempt exists, so this one failing says nothing about the payable. */
    private function superseded(Payable&Model $payable, Payment $payment): bool
    {
        return $payable->payments()->where('payments.id', '>', $payment->getKey())->exists();
    }

    /**
     * @return array{0: (Payable&Model)|null, 1: Payment}
     */
    private function lockBoth(Payment $payment): array
    {
        $payable = $payment->payableModel();
        $locked = $payable === null ? null : $this->lock($payable);

        return [$locked, Payment::query()->lockForUpdate()->whereKey($payment->id)->firstOrFail()];
    }

    /**
     * @template T of Payable&Model
     *
     * @param  T  $payable
     * @return T
     */
    private function lock(Payable&Model $payable): Payable&Model
    {
        /** @var T */
        return $payable->newQuery()->lockForUpdate()->findOrFail($payable->getKey());
    }
}

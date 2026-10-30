<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\PaymentUpdated;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Exceptions\GatewayNotConfigured;
use App\Payments\Exceptions\PaymentException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
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
 */
class Payments
{
    private const SETTLED = [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded];

    public function __construct(private readonly Container $container) {}

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

        [$amount, $attempt] = DB::transaction(function () use ($for) {
            $payable = $this->lock($for);

            if ($payable->paymentStatus() === PaymentStatus::Paid) {
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

            $from = $locked->status;
            $locked->status = PaymentStatus::Paid;
            $locked->meta = array_merge($locked->meta ?? [], [
                'taken_by' => $by->name,
                'taken_by_id' => $by->id,
                'taken_at' => now()->toIso8601String(),
            ]);
            $locked->save();

            if ($payable !== null && $payable->paymentStatus() !== PaymentStatus::Paid) {
                $payable->markPaid();
            }

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
                    'amount' => __('payments.refund.too_much', ['max' => '£'.number_format($remaining / 100, 2)]),
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
        $from = DB::transaction(function () use ($payment, $status, $message, $amount) {
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
            $locked->status = $target;

            if ($status === AttemptStatus::Failed) {
                $locked->meta = array_merge($locked->meta ?? [], ['failure' => $message ?? __('payments.declined')]);
            }

            $locked->save();

            if ($payable !== null && $payable->paymentStatus() !== PaymentStatus::Paid) {
                $target === PaymentStatus::Paid ? $payable->markPaid() : $payable->markPaymentFailed();
            }

            return $from;
        });

        if ($from === null) {
            return false;
        }

        $payment->refresh();
        PaymentUpdated::dispatch($payment, $from);

        return true;
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

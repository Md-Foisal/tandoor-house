<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Payments;
use App\Policies\Staff\OrderBoardPolicy;
use App\Support\ActivityRecorder;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Refunds an order through each payment's own gateway (Payments::refund):
 * card money goes back to the card, venue payments are recorded as cash.
 *
 * Everything the amount depends on (what was paid, what is already refunded,
 * the role's limit) is read again under a row lock, so two refunds at once
 * cannot together exceed either.
 */
class RefundOrder
{
    private const PAID_STATES = [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded];

    public function __construct(
        private readonly OrderBoardPolicy $policy,
        private readonly ActivityRecorder $activity,
        private readonly Payments $payments,
    ) {}

    /**
     * @param  array<int|string, mixed>  $requested  order line id => quantity, for "lines"
     * @return int pence refunded
     */
    public function __invoke(Order $order, string $kind, ?int $typed, array $requested, string $reason, User $by): int
    {
        [$done, $failure] = DB::transaction(function () use ($order, $kind, $typed, $requested, $reason, $by) {
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->firstOrFail();
            $payments = $locked->payments()->lockForUpdate()->get();

            if ($payments->isEmpty() && $this->policy->isRefundable($locked)) {
                $payments = collect([$locked->payments()->create([
                    'gateway' => 'venue',
                    'method' => $locked->payment_method,
                    'amount' => $locked->total,
                    'refunded' => 0,
                    'status' => PaymentStatus::Paid,
                ])]);
            }

            // Payments::refund reads the order through each payment.
            $payments->each(fn (Payment $p) => $p->setRelation('order', $locked));

            $eligible = $payments->filter(fn (Payment $p) => in_array($p->status, self::PAID_STATES, true));
            $refundable = (int) $eligible->sum(fn (Payment $p) => $p->amount - $p->refunded);
            $alreadyRefunded = (int) $payments->sum('refunded');
            $lines = [];

            $amount = match ($kind) {
                'full' => $refundable,
                'amount' => (int) $typed,
                default => $this->linesAmount($locked, $payments, $requested, $refundable, $lines),
            };

            $this->policy->refund($by, $locked, $amount, $alreadyRefunded)->authorize();

            if ($eligible->isEmpty()) {
                throw ValidationException::withMessages(['amount' => __('staff_orders.refund.nothing_paid')]);
            }

            if ($amount < 1 || $amount > $refundable) {
                throw ValidationException::withMessages([
                    'amount' => __('staff_orders.refund.too_much', ['max' => '£'.number_format($refundable / 100, 2)]),
                ]);
            }

            $left = $amount;
            $done = 0;
            $failure = null;

            foreach ($eligible as $payment) {
                $take = min($left, $payment->amount - $payment->refunded);

                if ($take <= 0) {
                    continue;
                }

                // The dishes are kept on the first payment that refunds, so
                // splitting across payments never counts them twice.
                $kept = $done === 0 ? $lines : [];

                try {
                    $this->payments->refund($payment, $take, $reason, $by, $kept);
                } catch (ValidationException $e) {
                    $failure = $e;
                } catch (PaymentException|ConnectionException) {
                    $failure = ValidationException::withMessages(['amount' => __('staff_orders.refund.gateway_unreachable')]);
                }

                if ($failure !== null) {
                    // Nothing went back yet: leave everything as it was. Otherwise
                    // keep what the gateway already returned and report the rest.
                    if ($done === 0) {
                        throw $failure;
                    }

                    break;
                }

                $done += $take;
                $left -= $take;
            }

            $locked->refresh();
            $order->setRawAttributes($locked->getAttributes(), true);

            $this->activity->record(
                'order.refunded',
                __('staff_orders.log.refunded', ['user' => $by->name, 'amount' => '£'.number_format($done / 100, 2), 'number' => $locked->number, 'reason' => $reason]),
                $locked,
                ['amount' => $done, 'reason' => $reason, 'lines' => $lines],
                $by,
            );

            return [$done, $failure];
        });

        if ($failure !== null) {
            throw ValidationException::withMessages(['amount' => __('staff_orders.refund.partial', [
                'done' => Money::format($done),
                'reason' => collect($failure->errors())->flatten()->first(),
            ])]);
        }

        return $done;
    }

    /**
     * The value of the chosen dishes as the guest paid for them: the unit
     * price already includes modifiers, and the order's discount is shared
     * across lines in proportion. Never more than what is left to refund, and
     * never a dish more times than it was bought and not yet refunded.
     *
     * @param  Collection<int, Payment>  $payments
     * @param  array<int|string, mixed>  $requested
     * @param  array<int, int>  $chosen  filled with the quantities that count
     */
    private function linesAmount(Order $order, Collection $payments, array $requested, int $refundable, array &$chosen): int
    {
        $order->load('lines');
        $taken = $this->refundedQuantities($payments);
        $payable = $order->subtotal - $order->discount;
        $amount = 0;

        /** @var OrderLine $line */
        foreach ($order->lines as $line) {
            $quantity = min((int) ($requested[$line->id] ?? 0), $line->quantity - ($taken[$line->id] ?? 0));

            if ($quantity > 0) {
                $chosen[$line->id] = $quantity;
                $gross = $line->unit_price * $quantity;
                $amount += $order->subtotal > 0 ? intdiv($gross * $payable, $order->subtotal) : $gross;
            }
        }

        if ($chosen === []) {
            throw ValidationException::withMessages(['lines' => __('staff_orders.refund.pick_lines')]);
        }

        return min($amount, $refundable);
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<int, int> order line id => quantity already refunded
     */
    private function refundedQuantities(Collection $payments): array
    {
        $taken = [];

        foreach ($payments as $payment) {
            foreach ($payment->meta['refunds'] ?? [] as $refund) {
                foreach ($refund['lines'] ?? [] as $lineId => $quantity) {
                    $taken[(int) $lineId] = ($taken[(int) $lineId] ?? 0) + (int) $quantity;
                }
            }
        }

        return $taken;
    }
}

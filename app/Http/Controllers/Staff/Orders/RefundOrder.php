<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\User;
use App\Policies\Staff\OrderBoardPolicy;
use App\Support\ActivityRecorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a refund against the order's payments. The gateway call is not made
 * here: the refund is bookkeeping, and card money goes back through the
 * payment provider's dashboard until the gateway is wired up.
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
    ) {}

    /**
     * @param  array<int|string, mixed>  $requested  order line id => quantity, for "lines"
     * @return int pence refunded
     */
    public function __invoke(Order $order, string $kind, ?int $typed, array $requested, string $reason, User $by): int
    {
        return DB::transaction(function () use ($order, $kind, $typed, $requested, $reason, $by) {
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

            foreach ($eligible as $payment) {
                $take = min($left, $payment->amount - $payment->refunded);

                if ($take <= 0) {
                    continue;
                }

                $meta = $payment->meta ?? [];
                $meta['refunds'][] = [
                    'amount' => $take,
                    'reason' => $reason,
                    'by' => $by->name,
                    'at' => now()->toIso8601String(),
                    'lines' => $lines,
                ];

                $payment->refunded += $take;
                $payment->status = $payment->refunded >= $payment->amount ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
                $payment->meta = $meta;
                $payment->save();
                $left -= $take;
            }

            $locked->payment_status = (int) $payments->sum('refunded') >= (int) $payments->sum('amount')
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded;
            $locked->save();

            $order->setRawAttributes($locked->getAttributes(), true);

            $this->activity->record(
                'order.refunded',
                __('staff_orders.log.refunded', ['user' => $by->name, 'amount' => '£'.number_format($amount / 100, 2), 'number' => $locked->number, 'reason' => $reason]),
                $locked,
                ['amount' => $amount, 'reason' => $reason, 'lines' => $lines],
                $by,
            );

            return $amount;
        });
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

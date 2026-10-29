<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a refund against the order's payments. The gateway call arrives in
 * Phase 4; until then the refund is bookkeeping only.
 */
class RefundOrder
{
    /**
     * @param  array<int, int>  $lines  order line id => quantity, when refunding by line
     */
    public function __invoke(Order $order, int $amount, string $reason, User $by, array $lines = []): void
    {
        DB::transaction(function () use ($order, $amount, $reason, $by, $lines) {
            $order->load('payments');

            if ($order->payments->isEmpty()) {
                $order->payments()->create([
                    'gateway' => 'venue',
                    'method' => $order->payment_method,
                    'amount' => $order->total,
                    'refunded' => 0,
                    'status' => PaymentStatus::Paid,
                ]);
                $order->load('payments');
            }

            $eligible = $order->payments->filter(fn (Payment $p) => in_array($p->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true));
            $refundable = (int) $eligible->sum(fn (Payment $p) => $p->amount - $p->refunded);

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

            $totalRefunded = (int) $order->payments->sum('refunded');
            $totalPaid = (int) $order->payments->sum('amount');
            $order->payment_status = $totalRefunded >= $totalPaid ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
            $order->save();

            OrderActivity::record(
                'order.refunded',
                $order,
                $by,
                __('staff_orders.log.refunded', ['user' => $by->name, 'amount' => '£'.number_format($amount / 100, 2), 'number' => $order->number, 'reason' => $reason]),
                ['amount' => $amount, 'reason' => $reason, 'lines' => $lines],
            );
        });
    }
}

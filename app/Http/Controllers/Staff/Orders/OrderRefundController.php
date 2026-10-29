<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Orders\RefundRequest;
use App\Models\Order;
use App\Policies\Staff\OrderBoardPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrderRefundController extends Controller
{
    public function __invoke(RefundRequest $request, Order $order, RefundOrder $refund): RedirectResponse
    {
        $policy = new OrderBoardPolicy;
        $user = $request->user();

        $order->load(['lines', 'payments']);
        ['paid' => $paid, 'refunded' => $refunded] = OrderData::paid($order);

        $lines = [];
        $amount = match ($request->string('kind')->toString()) {
            'full' => $paid - $refunded,
            'amount' => $request->integer('amount'),
            default => $this->linesAmount($order, $request->input('lines', []), $lines),
        };

        $policy->refund($user, $order, $amount, $refunded)->authorize();

        if (! $policy->isRefundable($order)) {
            throw ValidationException::withMessages(['amount' => __('staff_orders.refund.nothing_paid')]);
        }

        $refund($order, $amount, $request->string('reason')->trim()->toString(), $user, $lines);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('staff_orders.toast.refunded', ['amount' => '£'.number_format($amount / 100, 2)]),
        ]);

        return back();
    }

    /**
     * @param  array<int|string, mixed>  $requested  order line id => quantity
     * @param  array<int, int>  $chosen  filled with the lines that count
     */
    private function linesAmount(Order $order, array $requested, array &$chosen): int
    {
        $amount = 0;

        foreach ($order->lines as $line) {
            $quantity = min((int) ($requested[$line->id] ?? 0), $line->quantity);

            if ($quantity > 0) {
                $chosen[$line->id] = $quantity;
                $amount += $line->unit_price * $quantity;
            }
        }

        if ($amount === 0) {
            throw ValidationException::withMessages(['lines' => __('staff_orders.refund.pick_lines')]);
        }

        return $amount;
    }
}

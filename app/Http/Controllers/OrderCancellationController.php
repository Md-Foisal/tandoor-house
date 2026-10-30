<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\CancelOrderRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Payments;
use App\Services\OrderStateMachine;
use App\Support\ActivityRecorder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * A customer can call off an order only while it is still `placed`: once the
 * kitchen accepts, food is being made and they ring the restaurant instead.
 */
class OrderCancellationController extends Controller
{
    public function store(CancelOrderRequest $request, Order $order, OrderStateMachine $machine, Payments $payments, ActivityRecorder $activity): RedirectResponse
    {
        $user = $request->user();

        $refunded = DB::transaction(function () use ($order, $machine, $payments, $activity, $user) {
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->firstOrFail();

            if ($locked->status !== OrderStatus::Placed) {
                throw ValidationException::withMessages(['cancel' => __('tracking.cancel.too_late')]);
            }

            $machine->transition($locked, OrderStatus::Cancelled, $user, __('tracking.cancel.reason'));

            $refunded = $this->refundCard($locked, $payments, $user);

            $activity->record(
                'order.cancelled_by_customer',
                __('tracking.cancel.log', ['number' => $locked->number, 'refund' => __('tracking.cancel.log_refund_'.($refunded > 0 ? 'yes' : 'no'))]),
                $locked,
                ['refunded' => $refunded],
                $user,
            );

            return $refunded;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($refunded > 0 ? 'tracking.cancel.done_refunded' : 'tracking.cancel.done'),
        ]);

        return redirect()->route('orders.show', $order);
    }

    /**
     * Pay-at-venue orders have nothing settled, so there is nothing to send back.
     *
     * @return int pence refunded
     */
    private function refundCard(Order $order, Payments $payments, ?User $user): int
    {
        $paid = $order->payments()
            ->whereIn('status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded])
            ->get();

        // Payments::refund needs a name for the receipt; guests have no account.
        $actor = $user ?? (new User)->forceFill(['name' => __('tracking.cancel.guest_actor')]);
        $total = 0;

        /** @var Payment $payment */
        foreach ($paid as $payment) {
            $left = $payment->amount - $payment->refunded;

            if ($left < 1) {
                continue;
            }

            $payment->setRelation('order', $order);

            try {
                $payments->refund($payment, $left, __('tracking.cancel.reason'), $actor);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['cancel' => (string) collect($e->errors())->flatten()->first()]);
            } catch (PaymentException|ConnectionException) {
                throw ValidationException::withMessages(['cancel' => __('tracking.cancel.refund_failed')]);
            }

            $total += $left;
        }

        return $total;
    }
}

<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Orders\TakePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Payments;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Takes the money for a pay-at-venue or cash order. A card tapped on the
 * restaurant's own terminal is recorded as a card payment on the venue
 * gateway: the terminal, not this app, settled it.
 */
class OrderPaymentController extends Controller
{
    public function __invoke(TakePaymentRequest $request, Order $order, Payments $payments, ActivityRecorder $activity): RedirectResponse
    {
        $user = $request->user();
        $tender = $request->string('method')->toString() === 'card' ? PaymentMethod::Card : PaymentMethod::Cash;

        $payment = DB::transaction(function () use ($order, $tender) {
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->firstOrFail();
            $venue = $locked->payments()->lockForUpdate()->where('gateway', 'venue')->latest('id')->first();

            if ($venue === null && $locked->payments()->doesntExist()
                && in_array($locked->payment_method, [PaymentMethod::PayAtVenue, PaymentMethod::Cash], true)
                && $locked->payment_status === PaymentStatus::Pending) {
                $venue = $locked->payments()->create([
                    'gateway' => 'venue',
                    'method' => $locked->payment_method,
                    'amount' => $locked->total,
                    'refunded' => 0,
                    'status' => PaymentStatus::Pending,
                ]);
            }

            if ($venue === null) {
                throw ValidationException::withMessages(['method' => __('staff_orders.take_payment.nothing_due')]);
            }

            // Only while still pending: a second tap must not rewrite a settled payment.
            if ($venue->status === PaymentStatus::Pending) {
                $venue->forceFill([
                    'method' => $tender,
                    'meta' => array_merge($venue->meta ?? [], ['tender' => $tender === PaymentMethod::Card ? 'terminal' : 'cash']),
                ])->save();
                $locked->forceFill(['payment_method' => $tender])->save();
            }

            return $venue;
        });

        $before = $payment->status;
        $payments->takeAtVenue($payment, $user);

        if ($before === PaymentStatus::Pending) {
            $order->refresh();
            $activity->record(
                'order.payment_taken',
                __('staff_orders.log.payment_taken', [
                    'user' => $user->name,
                    'amount' => '£'.number_format($payment->amount / 100, 2),
                    'method' => __("staff_orders.take_payment.log_method.{$tender->value}"),
                    'number' => $order->number,
                ]),
                $order,
                ['amount' => $payment->amount, 'method' => $tender->value],
                $user,
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('staff_orders.toast.payment_taken', ['amount' => '£'.number_format($payment->amount / 100, 2)]),
        ]);

        return back();
    }
}

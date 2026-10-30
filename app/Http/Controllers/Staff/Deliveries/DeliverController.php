<?php

namespace App\Http\Controllers\Staff\Deliveries;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Deliveries\DeliverRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Policies\DeliveryPolicy;
use App\Services\OrderStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DeliverController extends Controller
{
    public function __invoke(DeliverRequest $request, Order $order, OrderStateMachine $machine, DeliveryPolicy $policy): RedirectResponse
    {
        DB::transaction(function () use ($request, $order, $machine, $policy) {
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->firstOrFail();

            $takesCash = $policy->collectsCash($locked);
            $tendered = $request->integer('cash_collected');

            if ($takesCash && $tendered < $locked->total) {
                throw ValidationException::withMessages(['cash_collected' => __('staff_deliveries.errors.cash_short')]);
            }

            $locked = $machine->transition($locked, OrderStatus::Delivered, $request->user());

            if (! $takesCash) {
                return;
            }

            $locked->forceFill([
                'cash_collected' => $tendered,
                'payment_method' => PaymentMethod::Cash,
                'payment_status' => PaymentStatus::Paid,
            ])->save();

            $payment = $locked->payments()->where('status', PaymentStatus::Pending)->lockForUpdate()->first() ?? new Payment(['order_id' => $locked->id]);
            $payment->fill([
                'gateway' => 'venue',
                'method' => PaymentMethod::Cash,
                'amount' => $locked->total,
                'status' => PaymentStatus::Paid,
                'meta' => [...($payment->meta ?? []), 'cash_tendered' => $tendered, 'change_given' => $tendered - $locked->total],
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_deliveries.toast.delivered', ['number' => $order->number])]);

        return back();
    }
}

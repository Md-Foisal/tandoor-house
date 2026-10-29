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
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DeliverController extends Controller
{
    public function __invoke(DeliverRequest $request, Order $order, OrderStateMachine $machine): RedirectResponse
    {
        $policy = new DeliveryPolicy;
        Gate::allowIf($policy->act($request->user(), $order));

        DB::transaction(function () use ($request, $order, $machine, $policy) {
            $machine->transition($order, OrderStatus::Delivered, $request->user());

            if (! $policy->collectsCash($order)) {
                return;
            }

            $cash = $request->integer('cash_collected');

            $order->forceFill([
                'cash_collected' => $cash,
                'payment_method' => PaymentMethod::Cash,
                'payment_status' => PaymentStatus::Paid,
            ])->save();

            $payment = $order->payments()->where('status', PaymentStatus::Pending)->first() ?? new Payment(['order_id' => $order->id]);
            $payment->fill([
                'gateway' => 'venue',
                'method' => PaymentMethod::Cash,
                'amount' => $cash,
                'status' => PaymentStatus::Paid,
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_deliveries.toast.delivered', ['number' => $order->number])]);

        return back();
    }
}

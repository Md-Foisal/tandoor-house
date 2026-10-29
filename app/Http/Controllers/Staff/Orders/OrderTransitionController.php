<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Orders\TransitionRequest;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderTransitionController extends Controller
{
    public function __invoke(TransitionRequest $request, Order $order, OrderStateMachine $machine): RedirectResponse
    {
        $to = $request->status();
        $reason = $request->filled('reason') ? $request->string('reason')->trim()->toString() : null;

        $machine->transition($order, $to, $request->user(), $reason);

        if (in_array($to, [OrderStatus::Accepted, OrderStatus::Rejected, OrderStatus::Cancelled], true)) {
            OrderActivity::record(
                'order.'.$to->value,
                $order,
                $request->user(),
                __('staff_orders.log.'.$to->value, ['user' => $request->user()->name, 'number' => $order->number, 'reason' => $reason]),
                ['reason' => $reason],
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('staff_orders.toast.moved', ['number' => $order->number, 'status' => __('staff.status.'.$to->value)]),
        ]);

        return back();
    }
}

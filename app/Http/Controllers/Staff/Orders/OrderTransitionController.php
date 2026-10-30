<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Orders\TransitionRequest;
use App\Models\Order;
use App\Policies\Staff\OrderBoardPolicy;
use App\Services\OrderStateMachine;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrderTransitionController extends Controller
{
    public function __invoke(
        TransitionRequest $request,
        Order $order,
        OrderStateMachine $machine,
        OrderBoardPolicy $policy,
        ActivityRecorder $activity,
    ): RedirectResponse {
        $to = $request->status();
        $user = $request->user();
        $reason = $request->filled('reason') ? $request->string('reason')->trim()->toString() : null;

        DB::transaction(function () use ($order, $to, $user, $reason, $machine, $policy, $activity) {
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->firstOrFail();

            $policy->transition($user, $locked, $to)->authorize();

            if ($to === OrderStatus::OutForDelivery && $locked->rider_id === null) {
                throw ValidationException::withMessages(['status' => __('staff_orders.transition.needs_rider')]);
            }

            $machine->transition($locked, $to, $user, $reason);

            if (in_array($to, [OrderStatus::Accepted, OrderStatus::Rejected, OrderStatus::Cancelled], true)) {
                $activity->record(
                    'order.'.$to->value,
                    __('staff_orders.log.'.$to->value, ['user' => $user->name, 'number' => $locked->number, 'reason' => $reason]),
                    $locked,
                    ['reason' => $reason],
                    $user,
                );
            }

            $order->setRawAttributes($locked->getAttributes(), true);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('staff_orders.toast.moved', ['number' => $order->number, 'status' => __('staff.status.'.$to->value)]),
        ]);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Staff\Deliveries;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Deliveries\PickupRequest;
use App\Models\Order;
use App\Policies\DeliveryPolicy;
use App\Services\OrderStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PickupController extends Controller
{
    public function __invoke(PickupRequest $request, Order $order, OrderStateMachine $machine, DeliveryPolicy $policy): RedirectResponse
    {
        DB::transaction(function () use ($request, $order, $machine, $policy) {
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->firstOrFail();

            abort_unless($policy->act($request->user(), $locked), 403);

            if ($locked->rider_id === null) {
                throw ValidationException::withMessages(['status' => __('staff_orders.transition.needs_rider')]);
            }

            $machine->transition($locked, OrderStatus::OutForDelivery, $request->user());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_deliveries.toast.picked_up', ['number' => $order->number])]);

        return back();
    }
}

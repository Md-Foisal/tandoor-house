<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Orders\AssignRiderRequest;
use App\Models\Order;
use App\Models\User;
use App\Policies\Staff\OrderBoardPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OrderRiderController extends Controller
{
    public function __invoke(AssignRiderRequest $request, Order $order): RedirectResponse
    {
        Gate::allowIf((new OrderBoardPolicy)->canAssignRider($request->user(), $order));

        $rider = $request->filled('rider_id') ? User::query()->findOrFail($request->integer('rider_id')) : null;

        $order->forceFill(['rider_id' => $rider?->id])->save();

        OrderActivity::record(
            'order.rider',
            $order,
            $request->user(),
            $rider === null
                ? __('staff_orders.log.rider_removed', ['user' => $request->user()->name, 'number' => $order->number])
                : __('staff_orders.log.rider_set', ['user' => $request->user()->name, 'number' => $order->number, 'rider' => $rider->name]),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_orders.toast.rider')]);

        return back();
    }
}

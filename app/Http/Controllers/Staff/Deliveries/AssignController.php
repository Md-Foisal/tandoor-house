<?php

namespace App\Http\Controllers\Staff\Deliveries;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Deliveries\AssignRequest;
use App\Models\Order;
use App\Models\User;
use App\Policies\DeliveryPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AssignController extends Controller
{
    public function __invoke(AssignRequest $request, Order $order): RedirectResponse
    {
        Gate::allowIf((new DeliveryPolicy)->assign($request->user(), $order));

        $rider = $request->filled('rider_id') ? User::query()->findOrFail($request->integer('rider_id')) : null;

        $order->forceFill(['rider_id' => $rider?->id])->save();

        DeliveryActivity::record(
            'order.rider',
            $order,
            $request->user(),
            $rider === null
                ? __('staff_deliveries.log.rider_removed', ['user' => $request->user()->name, 'number' => $order->number])
                : __('staff_deliveries.log.rider_set', ['user' => $request->user()->name, 'number' => $order->number, 'rider' => $rider->name]),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_deliveries.toast.assigned')]);

        return back();
    }
}

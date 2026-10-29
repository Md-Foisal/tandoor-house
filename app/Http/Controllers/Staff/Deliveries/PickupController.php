<?php

namespace App\Http\Controllers\Staff\Deliveries;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Policies\DeliveryPolicy;
use App\Services\OrderStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PickupController extends Controller
{
    public function __invoke(Request $request, Order $order, OrderStateMachine $machine): RedirectResponse
    {
        Gate::allowIf((new DeliveryPolicy)->act($request->user(), $order));

        $machine->transition($order, OrderStatus::OutForDelivery, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_deliveries.toast.picked_up', ['number' => $order->number])]);

        return back();
    }
}

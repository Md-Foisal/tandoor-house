<?php

namespace App\Http\Controllers\Staff\Deliveries;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Deliveries\PickupRequest;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PickupController extends Controller
{
    public function __invoke(PickupRequest $request, Order $order, OrderStateMachine $machine): RedirectResponse
    {
        $machine->transition($order, OrderStatus::OutForDelivery, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_deliveries.toast.picked_up', ['number' => $order->number])]);

        return back();
    }
}

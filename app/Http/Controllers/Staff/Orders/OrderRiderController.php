<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Actions\Deliveries\AssignRider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Orders\AssignRiderRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderRiderController extends Controller
{
    public function __invoke(AssignRiderRequest $request, Order $order, AssignRider $assign): RedirectResponse
    {
        $rider = $request->filled('rider_id') ? User::query()->findOrFail($request->integer('rider_id')) : null;

        $assign($order, $rider, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_orders.toast.rider')]);

        return back();
    }
}

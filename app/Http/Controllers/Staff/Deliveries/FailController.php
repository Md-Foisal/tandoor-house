<?php

namespace App\Http\Controllers\Staff\Deliveries;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Deliveries\FailRequest;
use App\Models\Order;
use App\Policies\DeliveryPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FailController extends Controller
{
    /** The order stays out for delivery; the log row is how managers hear about it. */
    public function __invoke(FailRequest $request, Order $order): RedirectResponse
    {
        Gate::allowIf((new DeliveryPolicy)->act($request->user(), $order));

        if ($order->status !== OrderStatus::OutForDelivery) {
            throw ValidationException::withMessages(['reason' => __('staff_deliveries.errors.not_on_the_road')]);
        }

        $reason = $request->string('reason')->trim()->toString();

        DeliveryActivity::record(
            'delivery.failed',
            $order,
            $request->user(),
            __('staff_deliveries.log.failed', ['user' => $request->user()->name, 'number' => $order->number, 'reason' => $reason]),
            ['reason' => $reason, 'rider_id' => $order->rider_id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_deliveries.toast.failed')]);

        return back();
    }
}

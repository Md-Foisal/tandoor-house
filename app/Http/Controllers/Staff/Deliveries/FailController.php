<?php

namespace App\Http\Controllers\Staff\Deliveries;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Deliveries\FailRequest;
use App\Models\Order;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FailController extends Controller
{
    /** The order stays out for delivery; the log row is how managers hear about it. */
    public function __invoke(FailRequest $request, Order $order, ActivityRecorder $activity): RedirectResponse
    {
        if ($order->status !== OrderStatus::OutForDelivery) {
            throw ValidationException::withMessages(['reason' => __('staff_deliveries.errors.not_on_the_road')]);
        }

        $reason = $request->string('reason')->trim()->toString();

        $activity->record(
            'delivery.failed',
            __('staff_deliveries.log.failed', ['user' => $request->user()->name, 'number' => $order->number, 'reason' => $reason]),
            $order,
            ['reason' => $reason, 'rider_id' => $order->rider_id],
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_deliveries.toast.failed')]);

        return back();
    }
}

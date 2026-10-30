<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Orders\RefundRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderRefundController extends Controller
{
    public function __invoke(RefundRequest $request, Order $order, RefundOrder $refund): RedirectResponse
    {
        $amount = $refund(
            $order,
            $request->string('kind')->toString(),
            $request->filled('amount') ? $request->integer('amount') : null,
            (array) $request->input('lines', []),
            $request->string('reason')->trim()->toString(),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('staff_orders.toast.refunded', ['amount' => '£'.number_format($amount / 100, 2)]),
        ]);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Staff\Deliveries;

use App\Actions\Deliveries\AssignRider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Deliveries\AssignRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AssignController extends Controller
{
    public function __invoke(AssignRequest $request, Order $order, AssignRider $assign): RedirectResponse
    {
        $rider = $request->filled('rider_id') ? User::query()->whereKey($request->integer('rider_id'))->firstOrFail() : null;

        $assign($order, $rider, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_deliveries.toast.assigned')]);

        return back();
    }
}

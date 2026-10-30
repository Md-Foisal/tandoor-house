<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Actions\Inventory\PurchaseOrders;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Purchase orders carry supplier costs, so every step is for managers and owners. */
class PurchaseOrderTransitionController extends Controller
{
    public function send(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders): RedirectResponse
    {
        Gate::authorize('send', $purchaseOrder);

        $orders->send($purchaseOrder, $request->user());

        return $this->done('sent', $purchaseOrder);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders): RedirectResponse
    {
        Gate::authorize('receive', $purchaseOrder);

        $orders->receive($purchaseOrder, $request->user());

        return $this->done('received', $purchaseOrder);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders): RedirectResponse
    {
        Gate::authorize('cancel', $purchaseOrder);

        $orders->cancel($purchaseOrder, $request->user());

        return $this->done('cancelled', $purchaseOrder);
    }

    private function done(string $key, PurchaseOrder $order): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __("staff_inventory.toast.po_{$key}", ['reference' => $order->reference])]);

        return back();
    }
}

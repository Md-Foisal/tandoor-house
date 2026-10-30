<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Actions\Inventory\PurchaseOrders;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Policies\InventoryPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Purchase orders carry supplier costs, so every step is for managers and owners. */
class PurchaseOrderTransitionController extends Controller
{
    public function send(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders, InventoryPolicy $policy): RedirectResponse
    {
        abort_unless($policy->manage($request->user()), 403);

        $orders->send($purchaseOrder, $request->user());

        return $this->done('sent', $purchaseOrder);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders, InventoryPolicy $policy): RedirectResponse
    {
        abort_unless($policy->manage($request->user()), 403);

        $orders->receive($purchaseOrder, $request->user());

        return $this->done('received', $purchaseOrder);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders, InventoryPolicy $policy): RedirectResponse
    {
        abort_unless($policy->manage($request->user()), 403);

        $orders->cancel($purchaseOrder, $request->user());

        return $this->done('cancelled', $purchaseOrder);
    }

    private function done(string $key, PurchaseOrder $order): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __("staff_inventory.toast.po_{$key}", ['reference' => $order->reference])]);

        return back();
    }
}

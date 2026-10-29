<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Actions\Inventory\PurchaseOrders;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Policies\InventoryPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PurchaseOrderTransitionController extends Controller
{
    public function send(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders): RedirectResponse
    {
        Gate::allowIf((new InventoryPolicy)->manage($request->user()));

        $orders->send($purchaseOrder, $request->user());

        return $this->done('sent', $purchaseOrder);
    }

    /** A delivery turning up is a stock-in, so the kitchen may record it. */
    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders): RedirectResponse
    {
        Gate::allowIf((new InventoryPolicy)->log($request->user()));

        $orders->receive($purchaseOrder, $request->user());

        return $this->done('received', $purchaseOrder);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders): RedirectResponse
    {
        Gate::allowIf((new InventoryPolicy)->manage($request->user()));

        $orders->cancel($purchaseOrder, $request->user());

        return $this->done('cancelled', $purchaseOrder);
    }

    private function done(string $key, PurchaseOrder $order): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __("staff_inventory.toast.po_{$key}", ['reference' => $order->reference])]);

        return back();
    }
}

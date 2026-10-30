<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Actions\Inventory\PurchaseOrders;
use App\Http\Controllers\Controller;
use App\Mail\PurchaseOrderToSupplier;
use App\Models\PurchaseOrder;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

/** Purchase orders carry supplier costs, so every step is for managers and owners. */
class PurchaseOrderTransitionController extends Controller
{
    public function send(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders, ActivityRecorder $activity): RedirectResponse
    {
        Gate::authorize('send', $purchaseOrder);

        $orders->send($purchaseOrder, $request->user());

        $note = $this->emailSupplier($request, $purchaseOrder, $activity);

        return $this->done('sent', $purchaseOrder, $note);
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

    /** Returns a note for the toast when the supplier could not be emailed. */
    private function emailSupplier(Request $request, PurchaseOrder $order, ActivityRecorder $activity): ?string
    {
        $supplier = $order->supplier;
        $email = trim((string) $supplier->email);

        if ($email === '') {
            return __('automations.stock.supplier_mail.no_email', ['supplier' => $supplier->name]);
        }

        Mail::to($email, $supplier->contact_name ?: $supplier->name)->queue(new PurchaseOrderToSupplier($order));

        $activity->record('inventory.po_emailed', __('automations.stock.supplier_mail.logged', [
            'user' => $request->user()->name, 'reference' => $order->reference, 'supplier' => $supplier->name, 'email' => $email,
        ]), $order, ['email' => $email], $request->user());

        return null;
    }

    private function done(string $key, PurchaseOrder $order, ?string $note = null): RedirectResponse
    {
        $message = __("staff_inventory.toast.po_{$key}", ['reference' => $order->reference]);

        Inertia::flash('toast', $note === null
            ? ['type' => 'success', 'message' => $message]
            : ['type' => 'info', 'message' => "{$message} {$note}"]);

        return back();
    }
}

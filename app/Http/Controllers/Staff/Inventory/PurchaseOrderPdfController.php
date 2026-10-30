<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Policies\InventoryPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PurchaseOrderPdfController extends Controller
{
    public function __invoke(Request $request, PurchaseOrder $purchaseOrder, InventoryPolicy $policy): Response
    {
        abort_unless($policy->manage($request->user()), 403);
        abort_if($purchaseOrder->status === PurchaseOrderStatus::Cancelled, 404);

        $purchaseOrder->load(['supplier', 'lines.ingredient']);

        return Pdf::loadView('pdf.purchase-order', [
            'order' => $purchaseOrder,
            'restaurant' => config('restaurant'),
        ])->setPaper('a4')->download("{$purchaseOrder->reference}.pdf");
    }
}

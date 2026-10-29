<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PurchaseOrderPdfController extends Controller
{
    public function __invoke(PurchaseOrder $purchaseOrder): Response
    {
        abort_if($purchaseOrder->status === PurchaseOrderStatus::Cancelled, 404);

        $purchaseOrder->load(['supplier', 'lines.ingredient']);

        return Pdf::loadView('pdf.purchase-order', [
            'order' => $purchaseOrder,
            'restaurant' => config('restaurant'),
        ])->setPaper('a4')->download("{$purchaseOrder->reference}.pdf");
    }
}

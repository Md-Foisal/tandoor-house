<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Inventory\SupplierRequest;
use App\Models\Supplier;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Supplier::class);

        return Inertia::render('staff/inventory/suppliers', [
            'abilities' => InventoryData::abilities($request->user()),
            'lowCount' => InventoryData::lowCount(),
            'suppliers' => Inertia::defer(fn () => Supplier::query()
                ->withCount([
                    'ingredients',
                    'purchaseOrders as open_orders_count' => fn ($query) => $query->whereIn('status', [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent]),
                ])
                ->orderBy('name')->get()
                ->map(fn (Supplier $s) => InventoryData::supplier($s, (int) $s->getAttribute('ingredients_count'), (int) $s->getAttribute('open_orders_count')))
                ->all()),
        ]);
    }

    public function store(SupplierRequest $request, ActivityRecorder $activity): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        $activity->record('inventory.supplier_created', __('staff_inventory.log.supplier_created', [
            'user' => $request->user()->name, 'supplier' => $supplier->name,
        ]), $supplier, by: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.supplier_created', ['name' => $supplier->name])]);

        return back();
    }

    public function update(SupplierRequest $request, Supplier $supplier, ActivityRecorder $activity): RedirectResponse
    {
        $supplier->update($request->validated());

        $activity->record('inventory.supplier_updated', __('staff_inventory.log.supplier_updated', [
            'user' => $request->user()->name, 'supplier' => $supplier->name,
        ]), $supplier, by: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.supplier_saved', ['name' => $supplier->name])]);

        return back();
    }
}

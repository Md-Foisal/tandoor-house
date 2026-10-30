<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Actions\Inventory\PurchaseOrders;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Inventory\PurchaseOrderLinesRequest;
use App\Http\Requests\Staff\Inventory\PurchaseOrderStoreRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $status = PurchaseOrderStatus::tryFrom((string) $request->query('status'));

        return Inertia::render('staff/inventory/purchase-orders', [
            'filters' => ['status' => $status?->value],
            'abilities' => InventoryData::abilities($request->user()),
            'lowCount' => InventoryData::lowCount(),
            'counts' => fn () => PurchaseOrder::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'suppliers' => fn () => Supplier::query()->orderBy('name')->get()->map(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'low' => count(app(PurchaseOrders::class)->suggestions($s)),
            ])->all(),
            'orders' => Inertia::defer(fn () => PurchaseOrder::query()
                ->with('supplier:id,name')
                ->withCount('lines')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit(100)->get()
                ->map(fn (PurchaseOrder $o) => [
                    'id' => $o->id,
                    'reference' => $o->reference,
                    'supplier' => $o->supplier->name,
                    'status' => $o->status->value,
                    'lines' => (int) $o->getAttribute('lines_count'),
                    'total' => $o->total,
                    'sent_at' => $o->sent_at?->toIso8601String(),
                    'received_at' => $o->received_at?->toIso8601String(),
                    'created_at' => $o->created_at?->toIso8601String(),
                ])->all()),
        ]);
    }

    public function store(PurchaseOrderStoreRequest $request, PurchaseOrders $orders): RedirectResponse
    {
        $supplier = Supplier::query()->findOrFail($request->integer('supplier_id'));
        $order = $orders->draftFor($supplier, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.po_created', ['reference' => $order->reference])]);

        return to_route('staff.inventory.purchase-orders.show', $order);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        Gate::authorize('view', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'lines.ingredient']);

        return Inertia::render('staff/inventory/purchase-order', [
            'abilities' => InventoryData::abilities($request->user()),
            'lowCount' => InventoryData::lowCount(),
            'ingredients' => fn () => InventoryData::options(),
            'order' => [
                'id' => $purchaseOrder->id,
                'reference' => $purchaseOrder->reference,
                'status' => $purchaseOrder->status->value,
                'total' => $purchaseOrder->total,
                'sent_at' => $purchaseOrder->sent_at?->toIso8601String(),
                'received_at' => $purchaseOrder->received_at?->toIso8601String(),
                'created_at' => $purchaseOrder->created_at?->toIso8601String(),
                'supplier' => InventoryData::supplier($purchaseOrder->supplier),
                'lines' => $purchaseOrder->lines->sortBy(fn (PurchaseOrderLine $l) => $l->ingredient->name)->map(fn (PurchaseOrderLine $l) => [
                    'ingredient_id' => $l->ingredient_id,
                    'name' => $l->ingredient->name,
                    'unit' => $l->ingredient->unit,
                    'quantity' => $l->quantity,
                    'unit_cost' => $l->unit_cost,
                    'total' => (int) round($l->quantity * $l->unit_cost / 1000),
                    'stock' => $l->ingredient->stock,
                    'par_level' => $l->ingredient->par_level,
                ])->values()->all(),
            ],
        ]);
    }

    public function update(PurchaseOrderLinesRequest $request, PurchaseOrder $purchaseOrder, PurchaseOrders $orders): RedirectResponse
    {
        /** @var list<array{ingredient_id: int, quantity: int}> $lines */
        $lines = array_values($request->validated('lines'));
        $orders->replaceLines($purchaseOrder, $lines, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_inventory.toast.po_saved', ['reference' => $purchaseOrder->reference])]);

        return back();
    }
}

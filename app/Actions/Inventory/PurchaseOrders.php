<?php

namespace App\Actions\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ActivityRecorder;
use App\Support\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrders
{
    public function __construct(private StockLedger $ledger, private ActivityRecorder $activity) {}

    /**
     * What to order from a supplier: each low ingredient, enough to reach its par level.
     *
     * @return list<array{ingredient: Ingredient, quantity: int}>
     */
    public function suggestions(Supplier $supplier): array
    {
        $rows = [];

        $ingredients = Ingredient::query()
            ->where('supplier_id', $supplier->id)
            ->whereColumn('stock', '<=', 'low_threshold')
            ->orderBy('name')
            ->get();

        foreach ($ingredients as $ingredient) {
            $quantity = $ingredient->par_level - $ingredient->stock;

            if ($quantity > 0) {
                $rows[] = ['ingredient' => $ingredient, 'quantity' => $quantity];
            }
        }

        return $rows;
    }

    public function draftFor(Supplier $supplier, User $by): PurchaseOrder
    {
        return DB::transaction(function () use ($supplier, $by) {
            $order = PurchaseOrder::create([
                'reference' => CodeGenerator::unique(PurchaseOrder::class, 'reference', 'PO-', 8),
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Draft,
                'total' => 0,
                'created_by' => $by->id,
            ]);

            $this->setLines($order, array_map(
                fn (array $row) => ['ingredient_id' => $row['ingredient']->id, 'quantity' => $row['quantity']],
                $this->suggestions($supplier),
            ));

            $this->activity->record('inventory.po_created', __('staff_inventory.log.po_created', [
                'user' => $by->name, 'reference' => $order->reference, 'supplier' => $supplier->name,
            ]), $order, by: $by);

            return $order;
        });
    }

    /**
     * The low-stock automation's order: tops up the supplier's open draft with this ingredient (up to par)
     * or starts one. Returns null when the ingredient has no supplier or nothing to order.
     */
    public function draftForLowStock(Ingredient $ingredient): ?PurchaseOrder
    {
        if ($ingredient->supplier_id === null) {
            return null;
        }

        return DB::transaction(function () use ($ingredient) {
            $fresh = Ingredient::query()->whereKey($ingredient->id)->firstOrFail();
            $quantity = $fresh->par_level - $fresh->stock;

            if ($quantity <= 0) {
                return null;
            }

            $order = PurchaseOrder::query()
                ->where('supplier_id', $fresh->supplier_id)
                ->where('status', PurchaseOrderStatus::Draft)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $created = $order === null;

            $order ??= PurchaseOrder::create([
                'reference' => CodeGenerator::unique(PurchaseOrder::class, 'reference', 'PO-', 8),
                'supplier_id' => $fresh->supplier_id,
                'status' => PurchaseOrderStatus::Draft,
                'total' => 0,
            ]);

            $lines = $order->lines()->get()
                ->reject(fn ($line) => $line->ingredient_id === $fresh->id)
                ->map(fn ($line) => ['ingredient_id' => $line->ingredient_id, 'quantity' => $line->quantity])
                ->push(['ingredient_id' => $fresh->id, 'quantity' => $quantity])
                ->all();

            $this->setLines($order, $lines);

            $this->activity->record('automation.stock.draft_order', __($created ? 'automations.stock.draft_started' : 'automations.stock.draft_extended', [
                'reference' => $order->reference, 'ingredient' => $fresh->name,
            ]), $order, ['ingredient_id' => $fresh->id, 'quantity' => $quantity], automated: true);

            return $order;
        });
    }

    /**
     * @param  array<int, array{ingredient_id: int, quantity: int}>  $lines
     */
    public function replaceLines(PurchaseOrder $order, array $lines, User $by): void
    {
        DB::transaction(function () use ($order, $lines, $by) {
            $locked = $this->lock($order, [PurchaseOrderStatus::Draft], 'staff_inventory.errors.po_not_draft');

            $this->setLines($locked, $lines);

            $this->activity->record('inventory.po_edited', __('staff_inventory.log.po_edited', [
                'user' => $by->name, 'reference' => $locked->reference,
            ]), $locked, by: $by);
        });

        $order->refresh();
    }

    public function send(PurchaseOrder $order, User $by): void
    {
        DB::transaction(function () use ($order, $by) {
            $locked = $this->lock($order, [PurchaseOrderStatus::Draft], 'staff_inventory.errors.po_not_draft');

            if (! $locked->lines()->exists()) {
                throw ValidationException::withMessages(['status' => __('staff_inventory.errors.po_empty')]);
            }

            $locked->forceFill(['status' => PurchaseOrderStatus::Sent, 'sent_at' => now()])->save();

            $this->activity->record('inventory.po_sent', __('staff_inventory.log.po_sent', [
                'user' => $by->name, 'reference' => $locked->reference,
            ]), $locked, by: $by);
        });

        $order->refresh();
    }

    public function receive(PurchaseOrder $order, User $by): void
    {
        DB::transaction(function () use ($order, $by) {
            $locked = $this->lock($order, [PurchaseOrderStatus::Sent], 'staff_inventory.errors.po_not_sent');

            foreach ($locked->lines()->with('ingredient')->get() as $line) {
                $this->ledger->stockIn($line->ingredient, $line->quantity, __('staff_inventory.movements.delivery_against', ['reference' => $locked->reference]), $by);
            }

            $locked->forceFill(['status' => PurchaseOrderStatus::Received, 'received_at' => now()])->save();

            $this->activity->record('inventory.po_received', __('staff_inventory.log.po_received', [
                'user' => $by->name, 'reference' => $locked->reference,
            ]), $locked, by: $by);
        });

        $order->refresh();
    }

    public function cancel(PurchaseOrder $order, User $by): void
    {
        DB::transaction(function () use ($order, $by) {
            $locked = $this->lock($order, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent], 'staff_inventory.errors.po_closed');

            $locked->forceFill(['status' => PurchaseOrderStatus::Cancelled])->save();

            $this->activity->record('inventory.po_cancelled', __('staff_inventory.log.po_cancelled', [
                'user' => $by->name, 'reference' => $locked->reference,
            ]), $locked, by: $by);
        });

        $order->refresh();
    }

    /**
     * @param  array<int, array{ingredient_id: int, quantity: int}>  $lines
     */
    private function setLines(PurchaseOrder $order, array $lines): void
    {
        $costs = Ingredient::query()->whereIn('id', array_column($lines, 'ingredient_id'))->pluck('cost_per_thousand', 'id');
        $total = 0;

        $order->lines()->delete();

        foreach ($lines as $line) {
            $unitCost = (int) $costs[$line['ingredient_id']];
            $order->lines()->create(['ingredient_id' => $line['ingredient_id'], 'quantity' => $line['quantity'], 'unit_cost' => $unitCost]);
            $total += (int) round($line['quantity'] * $unitCost / 1000);
        }

        $order->forceFill(['total' => $total])->save();
    }

    /**
     * Re-reads the order under a row lock so two people pressing at once cannot both act on the old status.
     *
     * @param  list<PurchaseOrderStatus>  $allowed
     */
    private function lock(PurchaseOrder $order, array $allowed, string $message): PurchaseOrder
    {
        $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

        if (! in_array($locked->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __($message)]);
        }

        return $locked;
    }
}

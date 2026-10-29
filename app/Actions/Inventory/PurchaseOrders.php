<?php

namespace App\Actions\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrders
{
    public function __construct(private StockLedger $ledger) {}

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
                'reference' => $this->nextReference(),
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Draft,
                'total' => 0,
                'created_by' => $by->id,
            ]);

            $this->setLines($order, array_map(
                fn (array $row) => ['ingredient_id' => $row['ingredient']->id, 'quantity' => $row['quantity']],
                $this->suggestions($supplier),
            ));

            InventoryActivity::record('inventory.po_created', $order, $by, __('staff_inventory.log.po_created', [
                'user' => $by->name, 'reference' => $order->reference, 'supplier' => $supplier->name,
            ]));

            return $order;
        });
    }

    /**
     * @param  array<int, array{ingredient_id: int, quantity: int}>  $lines
     */
    public function replaceLines(PurchaseOrder $order, array $lines, User $by): void
    {
        $this->ensure($order, PurchaseOrderStatus::Draft, 'staff_inventory.errors.po_not_draft');

        DB::transaction(function () use ($order, $lines, $by) {
            $this->setLines($order, $lines);

            InventoryActivity::record('inventory.po_edited', $order, $by, __('staff_inventory.log.po_edited', [
                'user' => $by->name, 'reference' => $order->reference,
            ]));
        });
    }

    public function send(PurchaseOrder $order, User $by): void
    {
        $this->ensure($order, PurchaseOrderStatus::Draft, 'staff_inventory.errors.po_not_draft');

        if (! $order->lines()->exists()) {
            throw ValidationException::withMessages(['status' => __('staff_inventory.errors.po_empty')]);
        }

        $order->forceFill(['status' => PurchaseOrderStatus::Sent, 'sent_at' => now()])->save();

        InventoryActivity::record('inventory.po_sent', $order, $by, __('staff_inventory.log.po_sent', [
            'user' => $by->name, 'reference' => $order->reference,
        ]));
    }

    public function receive(PurchaseOrder $order, User $by): void
    {
        DB::transaction(function () use ($order, $by) {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensure($locked, PurchaseOrderStatus::Sent, 'staff_inventory.errors.po_not_sent');

            foreach ($locked->lines()->with('ingredient')->get() as $line) {
                $this->ledger->stockIn($line->ingredient, $line->quantity, __('staff_inventory.movements.delivery_against', ['reference' => $locked->reference]), $by);
            }

            $locked->forceFill(['status' => PurchaseOrderStatus::Received, 'received_at' => now()])->save();

            InventoryActivity::record('inventory.po_received', $locked, $by, __('staff_inventory.log.po_received', [
                'user' => $by->name, 'reference' => $locked->reference,
            ]));
        });

        $order->refresh();
    }

    public function cancel(PurchaseOrder $order, User $by): void
    {
        if (! in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent], true)) {
            throw ValidationException::withMessages(['status' => __('staff_inventory.errors.po_closed')]);
        }

        $order->forceFill(['status' => PurchaseOrderStatus::Cancelled])->save();

        InventoryActivity::record('inventory.po_cancelled', $order, $by, __('staff_inventory.log.po_cancelled', [
            'user' => $by->name, 'reference' => $order->reference,
        ]));
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

    private function ensure(PurchaseOrder $order, PurchaseOrderStatus $expected, string $message): void
    {
        if ($order->status !== $expected) {
            throw ValidationException::withMessages(['status' => __($message)]);
        }
    }

    private function nextReference(): string
    {
        $prefix = 'PO-'.now()->year.'-';
        $last = PurchaseOrder::query()->where('reference', 'like', $prefix.'%')->orderByDesc('reference')->value('reference');
        $next = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}

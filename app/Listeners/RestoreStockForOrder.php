<?php

namespace App\Listeners;

use App\Actions\Inventory\Quantity;
use App\Actions\Inventory\StockLedger;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\StockMovement;
use App\Support\Automation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * An order rejected or cancelled after it was accepted never used its
 * ingredients, so exactly what its sale took goes back (an adjust movement).
 */
class RestoreStockForOrder implements ShouldQueue
{
    public function __construct(private StockLedger $ledger, private Automation $automation) {}

    public function handle(OrderStatusChanged $event): void
    {
        if (! in_array($event->to, [OrderStatus::Rejected, OrderStatus::Cancelled], true) || ! $this->automation->enabled(Automation::STOCK_DEDUCTION)) {
            return;
        }

        DB::transaction(function () use ($event) {
            $order = Order::query()->whereKey($event->orderId)->lockForUpdate()->first();

            if ($order === null) {
                return;
            }

            $sales = StockMovement::query()
                ->with('ingredient')
                ->where('order_id', $order->id)
                ->where('type', StockMovementType::Sale)
                ->get();

            foreach ($sales as $sale) {
                $quantity = -$sale->quantity;
                $movement = $this->ledger->returnSale($sale->ingredient, $quantity, $order, __('automations.stock.return_reason', ['number' => $order->number]));

                if ($movement !== null) {
                    $this->automation->log('automation.stock.restored', __('automations.stock.restored', [
                        'ingredient' => $sale->ingredient->name,
                        'quantity' => Quantity::format($quantity, $sale->ingredient->unit),
                        'number' => $order->number,
                    ]), $order, ['ingredient_id' => $sale->ingredient_id, 'movement_id' => $movement->id]);
                }
            }
        });
    }
}

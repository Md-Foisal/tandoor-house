<?php

namespace App\Listeners;

use App\Actions\Inventory\Quantity;
use App\Actions\Inventory\StockLedger;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\RecipeLine;
use App\Support\Automation;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * An accepted order uses up the ingredients in its dishes' recipes. Recipes
 * belong to dishes, not to variants or modifiers, so those add nothing.
 */
class DeductStockForOrder implements ShouldQueue
{
    public function __construct(private StockLedger $ledger, private Automation $automation) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Accepted || ! $this->automation->enabled(Automation::STOCK_DEDUCTION)) {
            return;
        }

        $order = Order::with('lines')->find($event->orderId);

        if ($order === null) {
            return;
        }

        $needed = [];

        foreach ($order->lines as $line) {
            if ($line->menu_item_id === null) {
                continue;
            }

            foreach (RecipeLine::query()->where('menu_item_id', $line->menu_item_id)->get() as $recipe) {
                $needed[$recipe->ingredient_id] = ($needed[$recipe->ingredient_id] ?? 0) + $recipe->quantity * $line->quantity;
            }
        }

        foreach ($needed as $ingredientId => $quantity) {
            $ingredient = Ingredient::find($ingredientId);

            if ($ingredient === null) {
                continue;
            }

            $movement = $this->ledger->sale($ingredient, $quantity, $order, __('automations.stock.sale_reason', ['number' => $order->number]));

            if ($movement === null) {
                continue;
            }

            $short = $quantity + $movement->quantity;

            $this->automation->log('automation.stock.deducted', __('automations.stock.deducted', [
                'ingredient' => $ingredient->name,
                'quantity' => Quantity::format($quantity - $short, $ingredient->unit),
                'number' => $order->number,
            ]).($short > 0 ? ' '.__('automations.stock.short', ['quantity' => Quantity::format($short, $ingredient->unit)]) : ''), $order, [
                'ingredient_id' => $ingredient->id, 'movement_id' => $movement->id, 'shortfall' => $short,
            ]);
        }
    }
}

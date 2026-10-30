<?php

namespace App\Actions\Inventory;

use App\Enums\Role;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\Staff\LowStock;
use App\Support\Automation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Runs after every stock movement: hides dishes the kitchen can no longer make,
 * brings them back after a delivery, and raises the low-stock alert once per
 * crossing of the reorder level.
 */
class ReviewStockLevels implements ShouldQueue
{
    use Dispatchable, Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly int $ingredientId, public readonly int $stockBefore, public readonly int $movementId) {}

    public function handle(Automation $automation, PurchaseOrders $purchaseOrders): void
    {
        if (! $automation->enabled(Automation::STOCK_DEDUCTION)) {
            return;
        }

        $ingredient = Ingredient::find($this->ingredientId);
        $movement = StockMovement::find($this->movementId);

        if ($ingredient === null || $movement === null) {
            return;
        }

        $this->syncDishes($automation, $ingredient);

        $after = $this->stockBefore + $movement->quantity;

        if ($this->stockBefore > $ingredient->low_threshold && $after <= $ingredient->low_threshold) {
            $this->alert($automation, $purchaseOrders, $ingredient);
        }
    }

    private function syncDishes(Automation $automation, Ingredient $ingredient): void
    {
        $dishes = MenuItem::query()
            ->whereHas('recipeLines', fn ($query) => $query->where('ingredient_id', $ingredient->id))
            ->with('recipeLines.ingredient')
            ->get();

        foreach ($dishes as $dish) {
            $short = $dish->recipeLines->first(fn ($line) => $line->quantity > $line->ingredient->stock);

            if ($short !== null && $dish->is_available) {
                $now = now();
                MenuItem::query()->whereKey($dish->id)->update(['is_available' => false, 'sold_out_at' => $now, 'updated_at' => $now]);

                $automation->log('automation.stock.sold_out', __('automations.stock.sold_out', [
                    'dish' => $dish->name, 'ingredient' => $short->ingredient->name,
                ]), $dish, ['ingredient_id' => $short->ingredient_id]);
            }

            // Only undo our own hide: a manager who has touched the dish since keeps the last word.
            if ($short === null && ! $dish->is_available && $this->hiddenByStock($dish)) {
                MenuItem::query()->whereKey($dish->id)->update(['is_available' => true, 'sold_out_at' => null, 'updated_at' => now()]);

                $automation->log('automation.stock.back_on', __('automations.stock.back_on', [
                    'dish' => $dish->name, 'ingredient' => $ingredient->name,
                ]), $dish, ['ingredient_id' => $ingredient->id]);
            }
        }
    }

    private function hiddenByStock(MenuItem $dish): bool
    {
        $soldOutAt = $dish->getRawOriginal('sold_out_at');

        return $soldOutAt !== null && $soldOutAt === $dish->getRawOriginal('updated_at');
    }

    private function alert(Automation $automation, PurchaseOrders $purchaseOrders, Ingredient $ingredient): void
    {
        if (! Cache::add("low-stock:{$this->movementId}", true, now()->addDay())) {
            return;
        }

        $order = $purchaseOrders->draftForLowStock($ingredient);

        $automation->log('automation.stock.low', __('automations.stock.low', [
            'ingredient' => $ingredient->name,
            'stock' => Quantity::format($ingredient->stock, $ingredient->unit),
        ]), $ingredient, ['ingredient_id' => $ingredient->id, 'purchase_order_id' => $order?->id]);

        $managers = User::query()->whereIn('role', [Role::Manager, Role::Owner])->get();

        if ($managers->isNotEmpty()) {
            Notification::send($managers, new LowStock($ingredient, $order));
        }
    }
}

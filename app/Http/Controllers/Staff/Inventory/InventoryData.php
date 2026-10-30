<?php

namespace App\Http\Controllers\Staff\Inventory;

use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\InventoryPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Shapes shared by every stock page, so the front end sees one format.
 */
class InventoryData
{
    /**
     * @return array{manage: bool}
     */
    public static function abilities(User $user): array
    {
        return ['manage' => app(InventoryPolicy::class)->manage($user)];
    }

    /** Costs and stock value are supplier-side figures: managers and owners only. */
    public static function seesCosts(User $user): bool
    {
        return app(InventoryPolicy::class)->manage($user);
    }

    /** Shown on the Ingredients tab so the kitchen sees trouble from any tab. */
    public static function lowCount(): int
    {
        return Ingredient::query()->whereColumn('stock', '<=', 'low_threshold')->count();
    }

    /**
     * @return Builder<Ingredient>
     */
    public static function ingredientsWithLastMovement(): Builder
    {
        return Ingredient::query()->with('supplier:id,name')->addSelect([
            'last_movement_at' => StockMovement::query()->select('created_at')->whereColumn('ingredient_id', 'ingredients.id')->orderByDesc('created_at')->orderByDesc('id')->limit(1),
            'last_movement_type' => StockMovement::query()->select('type')->whereColumn('ingredient_id', 'ingredients.id')->orderByDesc('created_at')->orderByDesc('id')->limit(1),
        ])->addSelect('ingredients.*');
    }

    /**
     * @return array<string, mixed>
     */
    public static function ingredient(Ingredient $ingredient, bool $costs): array
    {
        $last = $ingredient->getAttribute('last_movement_at');

        return [
            'id' => $ingredient->id,
            'name' => $ingredient->name,
            'unit' => $ingredient->unit,
            'stock' => $ingredient->stock,
            'low_threshold' => $ingredient->low_threshold,
            'par_level' => $ingredient->par_level,
            'supplier' => $ingredient->supplier ? ['id' => $ingredient->supplier->id, 'name' => $ingredient->supplier->name] : null,
            'is_low' => $ingredient->stock <= $ingredient->low_threshold,
            'last_movement_at' => $last ? Carbon::parse($last)->toIso8601String() : null,
            'last_movement_type' => $ingredient->getAttribute('last_movement_type'),
            ...($costs ? [
                'cost_per_thousand' => $ingredient->cost_per_thousand,
                'value' => self::value($ingredient->stock, $ingredient->cost_per_thousand),
            ] : []),
        ];
    }

    /** Pence worth of stock at a cost per thousand base units. */
    public static function value(int $quantity, int $costPerThousand): int
    {
        return (int) round($quantity * $costPerThousand / 1000);
    }

    /**
     * Light list for pickers (waste form, recipe lines, purchase order lines).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function options(bool $costs = true): array
    {
        return Ingredient::query()->orderBy('name')->get(['id', 'name', 'unit', 'stock', 'par_level', 'cost_per_thousand', 'supplier_id'])
            ->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'stock' => $i->stock,
                'par_level' => $i->par_level,
                'supplier_id' => $i->supplier_id,
                ...($costs ? ['cost_per_thousand' => $i->cost_per_thousand] : []),
            ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function supplier(Supplier $supplier, int $ingredients = 0, int $openOrders = 0): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact_name' => $supplier->contact_name,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'lead_days' => $supplier->lead_days,
            'ingredients' => $ingredients,
            'open_orders' => $openOrders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function movement(StockMovement $movement, bool $costs): array
    {
        return [
            'id' => $movement->id,
            'type' => $movement->type->value,
            'quantity' => $movement->quantity,
            'reason' => $movement->reason,
            'order_number' => $movement->order?->number,
            'user' => $movement->user?->name,
            'at' => $movement->created_at->toIso8601String(),
            'ingredient' => ['id' => $movement->ingredient->id, 'name' => $movement->ingredient->name, 'unit' => $movement->ingredient->unit],
            ...($costs ? ['cost' => self::value(abs($movement->quantity), $movement->ingredient->cost_per_thousand)] : []),
        ];
    }
}

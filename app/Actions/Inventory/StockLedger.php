<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to ingredients.stock goes through here so the movement row,
 * the new level and the activity log are written together or not at all.
 */
class StockLedger
{
    public function __construct(private ActivityRecorder $activity) {}

    public function stockIn(Ingredient $ingredient, int $quantity, ?string $reason, User $by): StockMovement
    {
        return $this->apply($ingredient, StockMovementType::In, fn () => $quantity, $reason, $by, 'inventory.stock_in', 'staff_inventory.log.stock_in');
    }

    public function waste(Ingredient $ingredient, int $quantity, string $reason, User $by): StockMovement
    {
        return $this->apply($ingredient, StockMovementType::Waste, fn () => -$quantity, $reason, $by, 'inventory.waste', 'staff_inventory.log.waste');
    }

    /** A stock count: the movement is whatever it takes to reach the counted level. */
    public function adjust(Ingredient $ingredient, int $counted, string $reason, User $by): StockMovement
    {
        return $this->apply($ingredient, StockMovementType::Adjust, function (Ingredient $locked) use ($counted) {
            $delta = $counted - $locked->stock;

            if ($delta === 0) {
                throw ValidationException::withMessages(['counted' => __('staff_inventory.errors.no_change')]);
            }

            return $delta;
        }, $reason, $by, 'inventory.adjust', 'staff_inventory.log.adjust');
    }

    /**
     * @param  callable(Ingredient): int  $delta
     */
    private function apply(Ingredient $ingredient, StockMovementType $type, callable $delta, ?string $reason, User $by, string $event, string $line): StockMovement
    {
        return DB::transaction(function () use ($ingredient, $type, $delta, $reason, $by, $event, $line) {
            $locked = Ingredient::query()->whereKey($ingredient->id)->lockForUpdate()->firstOrFail();
            $change = $delta($locked);

            if ($locked->stock + $change < 0) {
                throw ValidationException::withMessages(['quantity' => __('staff_inventory.errors.not_enough')]);
            }

            $movement = $locked->movements()->create([
                'type' => $type,
                'quantity' => $change,
                'reason' => $reason,
                'user_id' => $by->id,
            ]);

            $before = $locked->stock;
            $locked->forceFill(['stock' => $before + $change])->save();

            $this->activity->record($event, __($line, [
                'user' => $by->name,
                'ingredient' => $locked->name,
                'quantity' => Quantity::format(abs($change), $locked->unit),
                'reason' => $reason ?? '',
            ]), $locked, ['ingredient_id' => $locked->id, 'movement_id' => $movement->id, 'before' => $before, 'after' => $locked->stock, 'reason' => $reason], $by);

            return $movement;
        });
    }
}

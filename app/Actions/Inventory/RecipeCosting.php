<?php

namespace App\Actions\Inventory;

use App\Models\RecipeLine;

class RecipeCosting
{
    /** Dishes earning less than this share of their VAT-exclusive price are flagged. */
    public const TARGET_MARGIN = 65.0;

    /**
     * Pence to make one portion. Summed before rounding so pennies do not drift.
     *
     * @param  iterable<RecipeLine>  $lines  with ingredient loaded
     */
    public static function cost(iterable $lines): int
    {
        $thousandths = 0;

        foreach ($lines as $line) {
            $thousandths += $line->quantity * $line->ingredient->cost_per_thousand;
        }

        return (int) round($thousandths / 1000);
    }

    /** The price without VAT; vat_rate is in basis points (2000 = 20%). */
    public static function netPrice(int $price, int $vatRate): int
    {
        return (int) round($price * 10000 / (10000 + $vatRate));
    }

    /** Gross margin as a percentage with one decimal, or null when there is no price to compare. */
    public static function margin(int $net, int $cost): ?float
    {
        return $net > 0 ? round(($net - $cost) / $net * 100, 1) : null;
    }
}

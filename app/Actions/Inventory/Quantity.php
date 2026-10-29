<?php

namespace App\Actions\Inventory;

/**
 * Ingredient quantities are whole numbers of g, ml or pieces; people read kg and litres.
 */
class Quantity
{
    public static function format(int $quantity, string $unit): string
    {
        $big = ['g' => 'kg', 'ml' => 'l'];

        if (isset($big[$unit]) && abs($quantity) >= 1000) {
            return number_format($quantity / 1000, 1).' '.$big[$unit];
        }

        return number_format($quantity).' '.$unit;
    }
}

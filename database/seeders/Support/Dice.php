<?php

namespace Database\Seeders\Support;

/**
 * Small random helpers shared by the demo seeders.
 */
final class Dice
{
    public static function chance(float $probability): bool
    {
        return mt_rand() / mt_getrandmax() < $probability;
    }

    public static function between(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    /**
     * @template T
     *
     * @param  array<T>  $items
     * @return T
     */
    public static function pick(array $items): mixed
    {
        $items = array_values($items);

        return $items[array_rand($items)];
    }

    /**
     * @template T
     *
     * @param  non-empty-list<array{0: T, 1: int|float}>  $options  value and weight pairs
     * @return T
     */
    public static function weighted(array $options): mixed
    {
        $total = array_sum(array_column($options, 1));
        $roll = mt_rand() / mt_getrandmax() * $total;

        foreach ($options as [$value, $weight]) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $value;
            }
        }

        return $options[array_key_last($options)][0];
    }
}

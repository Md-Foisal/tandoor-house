<?php

namespace App\Actions\Team;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Splits the week's tips by hours worked. Each person gets their exact
 * proportion rounded down; the leftover pence go one each to the largest
 * remainders (lowest user id first on a tie), so the shares always add up to
 * the pot.
 */
class TipSplit
{
    /**
     * @return array{total: int, orders: int}
     */
    public function pot(WeekRange $week): array
    {
        $orders = Order::query()
            ->where('status', OrderStatus::Completed)
            ->where('completed_at', '>=', $week->start)
            ->where('completed_at', '<', $week->end());

        return [
            'total' => (int) (clone $orders)->sum('tip'),
            'orders' => (clone $orders)->where('tip', '>', 0)->count(),
        ];
    }

    /**
     * @param  array<int, int>  $minutesByUser  user id => minutes worked
     * @return array<int, int> user id => pence
     */
    public static function allocate(int $total, array $minutesByUser): array
    {
        $minutesByUser = array_filter($minutesByUser, fn (int $minutes) => $minutes > 0);
        $all = array_sum($minutesByUser);

        if ($total <= 0 || $all === 0) {
            return array_map(fn () => 0, $minutesByUser);
        }

        $shares = [];
        $remainders = [];

        foreach ($minutesByUser as $id => $minutes) {
            $shares[$id] = intdiv($total * $minutes, $all);
            $remainders[$id] = ($total * $minutes) % $all;
        }

        $ids = array_keys($minutesByUser);
        usort($ids, fn (int $a, int $b) => [$remainders[$b], $a] <=> [$remainders[$a], $b]);

        $left = $total - array_sum($shares);

        foreach (array_slice($ids, 0, $left) as $id) {
            $shares[$id]++;
        }

        ksort($shares);

        return $shares;
    }
}

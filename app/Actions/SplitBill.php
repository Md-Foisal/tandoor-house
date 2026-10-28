<?php

namespace App\Actions;

/**
 * Divides a bill between guests. Every share is whole pence and the shares
 * always add up to the total exactly; leftover pence go on the first share.
 */
class SplitBill
{
    /**
     * @return list<int>
     */
    public function evenly(int $total, int $people): array
    {
        $people = max(1, $people);
        $base = intdiv($total, $people);
        $shares = array_fill(0, $people, $base);
        $shares[0] += $total - $base * $people;

        return $shares;
    }

    /**
     * Shares by what each guest ate. The charges on top of the food
     * (service, discounts) are spread in proportion to the food.
     *
     * @param  list<int>  $guestItems  food pence per guest
     * @return array{shares: list<int>, unclaimed: int}
     */
    public function byItems(int $total, int $foodTotal, array $guestItems): array
    {
        $claimed = array_sum($guestItems);
        $buckets = [...$guestItems, max(0, $foodTotal - $claimed)];
        $parts = $foodTotal > 0
            ? array_map(fn (int $food) => intdiv($food * $total, $foodTotal), $buckets)
            : array_fill(0, count($buckets), 0);

        $parts[0] += $total - array_sum($parts);
        $unclaimed = array_pop($parts);

        return ['shares' => $parts, 'unclaimed' => $unclaimed];
    }
}

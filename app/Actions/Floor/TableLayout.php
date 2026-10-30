<?php

namespace App\Actions\Floor;

use App\Enums\OrderStatus;
use App\Models\DiningTable;
use Illuminate\Support\Collection;

/**
 * Small geometry and grouping rules shared by the floor actions.
 */
class TableLayout
{
    /**
     * Two tables are adjacent when they touch or are one grid unit apart on
     * one axis and overlap on the other.
     */
    public static function adjacent(DiningTable $a, DiningTable $b): bool
    {
        $gapX = max($a->x - ($b->x + $b->width), $b->x - ($a->x + $a->width), 0);
        $gapY = max($a->y - ($b->y + $b->height), $b->y - ($a->y + $a->height), 0);

        return $gapX <= 1 && $gapY <= 1 && ($gapX === 0 || $gapY === 0);
    }

    /**
     * True when every table can be reached from the first through adjacent ones.
     *
     * @param  Collection<int, DiningTable>  $tables
     */
    public static function connected(Collection $tables): bool
    {
        $tables = $tables->values();

        if ($tables->count() < 2) {
            return true;
        }

        $reached = [0];
        $queue = [0];

        while ($queue !== []) {
            $current = array_pop($queue);

            foreach ($tables as $index => $candidate) {
                if (! in_array($index, $reached, true) && self::adjacent($tables[$current], $candidate)) {
                    $reached[] = $index;
                    $queue[] = $index;
                }
            }
        }

        return count($reached) === $tables->count();
    }

    /**
     * The table plus every table merged into it.
     *
     * @return Collection<int, DiningTable>
     */
    public static function group(DiningTable $lead): Collection
    {
        return DiningTable::query()->where('merged_into_id', $lead->id)->get()->prepend($lead);
    }

    public static function groupSeats(DiningTable $lead): int
    {
        return (int) self::group($lead)->sum('seats');
    }

    /**
     * Statuses of an order that is still on the table.
     *
     * @return list<OrderStatus>
     */
    public static function closedStatuses(): array
    {
        return [OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled];
    }
}

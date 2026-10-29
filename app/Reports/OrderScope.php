<?php

namespace App\Reports;

use App\Enums\OrderStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Which orders count towards a report. Timestamps are stored as Europe/London
 * wall-clock strings (the app timezone), so day and hour buckets can be cut
 * straight from the text with substr(), the same on SQLite and MySQL.
 */
final class OrderScope
{
    /**
     * Everything past "placed" that was not turned away.
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return array_values(array_map(
            fn (OrderStatus $status) => $status->value,
            array_filter(OrderStatus::cases(), fn (OrderStatus $s) => ! in_array($s, [OrderStatus::Placed, OrderStatus::Rejected, OrderStatus::Cancelled], true)),
        ));
    }

    public static function orders(ReportRange $range, bool $previous = false): Builder
    {
        $start = $previous ? $range->previousStart : $range->start;
        $end = $previous ? $range->previousEnd : $range->end;

        return DB::table('orders')
            ->whereIn('orders.status', self::statuses())
            ->where('orders.placed_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('orders.placed_at', '<', $end->format('Y-m-d H:i:s'))
            ->when($range->channel, fn (Builder $q) => $q->where('orders.channel', $range->channel?->value));
    }

    /**
     * Seconds between two columns, written for the connected database.
     *
     * @param  literal-string  $from
     * @param  literal-string  $to
     * @return literal-string
     */
    public static function secondsBetween(string $from, string $to): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "(strftime('%s', {$to}) - strftime('%s', {$from}))",
            'pgsql' => "EXTRACT(EPOCH FROM ({$to} - {$from}))",
            default => "TIMESTAMPDIFF(SECOND, {$from}, {$to})",
        };
    }
}

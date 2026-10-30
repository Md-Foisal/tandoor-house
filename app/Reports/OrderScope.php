<?php

namespace App\Reports;

use App\Enums\OrderStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Which orders count towards a report. Timestamps are stored as Europe/London
 * wall-clock strings (the app timezone), so day and hour buckets are cut
 * straight from the stored value without any time zone conversion.
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
     * "2026-10-29 13" style hour bucket for orders.placed_at, written for the connected database.
     *
     * @return literal-string
     */
    public static function hourBucket(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d %H', orders.placed_at)",
            'pgsql' => "to_char(orders.placed_at, 'YYYY-MM-DD HH24')",
            default => "DATE_FORMAT(orders.placed_at, '%Y-%m-%d %H')",
        };
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

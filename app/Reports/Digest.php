<?php

namespace App\Reports;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Reports\Sections\Breakdown;
use App\Reports\Sections\Items;
use App\Reports\Sections\Kitchen;
use App\Reports\Sections\Overview;
use Illuminate\Support\Facades\DB;

/**
 * The headline numbers of a period for the owner's emails. Every figure comes
 * from the same report sections as the Reports screen, so the two always agree.
 */
final class Digest
{
    private const TOP = 5;

    /**
     * @return array<string, mixed>
     */
    public static function build(ReportRange $range): array
    {
        $overview = (new Overview)->data($range);
        $breakdown = (new Breakdown)->data($range);
        $items = (new Items)->data($range);
        $kitchen = (new Kitchen)->data($range);

        $metrics = [];

        foreach ($overview['metrics'] as $metric) {
            $metrics[$metric['key']] = ['value' => $metric['value'], 'previous' => $metric['previous'], 'change' => $metric['change']];
        }

        $busiestDay = self::best($breakdown['daily']);
        $busiestHour = self::best($breakdown['hours']);

        return [
            'label' => $range->label(),
            'previous_label' => $range->previousLabel(),
            'metrics' => $metrics,
            'channels' => array_values(array_filter($breakdown['channels'], fn (array $row) => $row['orders'] > 0)),
            'payments' => array_values(array_filter($breakdown['payments'], fn (array $row) => $row['orders'] > 0)),
            'top' => array_slice($items['top_quantity'], 0, self::TOP),
            'slow' => array_slice($items['slow_quantity'], 0, self::TOP),
            'prep_minutes' => $kitchen['overall']['avg_minutes'],
            'timed_orders' => $kitchen['overall']['orders'],
            'busiest_day' => $busiestDay !== null && $busiestDay['sales'] > 0 ? $busiestDay : null,
            'busiest_hour' => $busiestHour !== null && $busiestHour['sales'] > 0 ? $busiestHour : null,
            'turned_away' => self::turnedAway($range),
            'bookings' => self::bookings($range),
            'waste' => self::waste($range),
            'low_stock' => self::lowStock(),
        ];
    }

    /**
     * The row with the most sales; the first one on a tie.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private static function best(array $rows): ?array
    {
        $best = null;

        foreach ($rows as $row) {
            if ($best === null || $row['sales'] > $best['sales']) {
                $best = $row;
            }
        }

        return $best;
    }

    public static function change(?float $percent): string
    {
        if ($percent === null) {
            return __('automations.reports.new');
        }

        $text = rtrim(rtrim(number_format(abs($percent), 1), '0'), '.').'%';

        return match (true) {
            $percent > 0 => __('automations.reports.up', ['percent' => $text]),
            $percent < 0 => __('automations.reports.down', ['percent' => $text]),
            default => __('automations.reports.flat'),
        };
    }

    /**
     * Orders placed in the period that were rejected or cancelled.
     */
    private static function turnedAway(ReportRange $range): int
    {
        return DB::table('orders')
            ->whereIn('status', [OrderStatus::Rejected->value, OrderStatus::Cancelled->value])
            ->where('placed_at', '>=', $range->start->format('Y-m-d H:i:s'))
            ->where('placed_at', '<', $range->end->format('Y-m-d H:i:s'))
            ->when($range->channel, fn ($q) => $q->where('channel', $range->channel?->value))
            ->count();
    }

    /**
     * @return array{count: int, covers: int, no_shows: int}
     */
    private static function bookings(ReportRange $range): array
    {
        $kept = Reservation::query()
            ->where('starts_at', '>=', $range->start->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $range->end->format('Y-m-d H:i:s'))
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::Seated, ReservationStatus::Completed]);

        $noShows = Reservation::query()
            ->where('starts_at', '>=', $range->start->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $range->end->format('Y-m-d H:i:s'))
            ->where('status', ReservationStatus::NoShow)
            ->count();

        return ['count' => (clone $kept)->count(), 'covers' => (int) (clone $kept)->sum('party_size'), 'no_shows' => $noShows];
    }

    /**
     * Cost in pence of stock written off in the period.
     *
     * @return array{cost: int, entries: int}
     */
    private static function waste(ReportRange $range): array
    {
        $row = StockMovement::query()
            ->join('ingredients', 'ingredients.id', '=', 'stock_movements.ingredient_id')
            ->where('stock_movements.type', StockMovementType::Waste)
            ->where('stock_movements.created_at', '>=', $range->start->format('Y-m-d H:i:s'))
            ->where('stock_movements.created_at', '<', $range->end->format('Y-m-d H:i:s'))
            ->selectRaw('count(*) as entries, coalesce(sum(round(abs(stock_movements.quantity) * ingredients.cost_per_thousand / 1000.0)), 0) as cost')
            ->first();

        return ['cost' => (int) ($row->cost ?? 0), 'entries' => (int) ($row->entries ?? 0)];
    }

    /**
     * @return list<array{name: string, stock: int, unit: string, threshold: int}>
     */
    private static function lowStock(): array
    {
        $rows = [];

        foreach (Ingredient::query()->whereColumn('stock', '<=', 'low_threshold')->orderBy('name')->get(['name', 'stock', 'unit', 'low_threshold']) as $i) {
            $rows[] = ['name' => $i->name, 'stock' => $i->stock, 'unit' => $i->unit, 'threshold' => $i->low_threshold];
        }

        return $rows;
    }
}

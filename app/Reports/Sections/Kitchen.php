<?php

namespace App\Reports\Sections;

use App\Enums\Station;
use App\Reports\OrderScope;
use App\Reports\ReportRange;
use App\Reports\ReportTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class Kitchen
{
    /**
     * @return array<string, mixed>
     */
    public function data(ReportRange $range): array
    {
        $overall = $this->aggregate($this->timed($range));

        $found = $this->timed($range)
            ->joinSub(DB::table('order_lines')->select('order_id', 'station')->distinct(), 'stations', 'stations.order_id', '=', 'orders.id')
            ->addSelect('stations.station as station')
            ->groupBy('stations.station');

        $byStation = [];
        $rows = $this->aggregateRows($found);

        foreach (Station::cases() as $station) {
            $row = $rows[$station->value] ?? null;
            $byStation[] = ['key' => $station->value, 'label' => $station->label(), ...$this->shape($row)];
        }

        $stations = array_values(array_filter($byStation, fn (array $row) => $row['orders'] > 0));
        $all = ['key' => 'all', 'label' => __('staff_reports.kitchen.overall'), ...$overall];

        $table = ReportTable::make('kitchen', __('staff_reports.kitchen.title'), [
            'label' => 'text|'.__('staff_reports.columns.station'),
            'orders' => 'int|'.__('staff_reports.columns.orders'),
            'avg_minutes' => 'minutes|'.__('staff_reports.columns.avg_prep'),
            'late' => 'int|'.__('staff_reports.columns.late'),
            'late_percent' => 'percent|'.__('staff_reports.columns.late_percent'),
        ], [$all, ...$stations], note: __('staff_reports.kitchen.note'));

        return ['overall' => $all, 'stations' => $stations, 'tables' => [$table->toArray()]];
    }

    /**
     * Orders in the period with both a start and a finish time.
     */
    private function timed(ReportRange $range): Builder
    {
        $seconds = OrderScope::secondsBetween('orders.accepted_at', 'orders.ready_at');
        $slowest = '(select max(menu_items.prep_minutes) from order_lines join menu_items on menu_items.id = order_lines.menu_item_id where order_lines.order_id = orders.id)';

        return OrderScope::orders($range)
            ->whereNotNull('orders.accepted_at')
            ->whereNotNull('orders.ready_at')
            ->whereColumn('orders.ready_at', '>=', 'orders.accepted_at')
            ->selectRaw(
                "count(*) as orders, coalesce(avg({$seconds}), 0) as avg_seconds, "
                .'coalesce(sum(case when orders.estimated_ready_at is not null then (case when orders.ready_at > orders.estimated_ready_at then 1 else 0 end) '
                ."when {$slowest} is not null and {$seconds} > {$slowest} * 60 then 1 else 0 end), 0) as late",
            );
    }

    /**
     * @return array{orders: int, avg_minutes: float, late: int, late_percent: float}
     */
    private function aggregate(Builder $query): array
    {
        return $this->shape($query->first());
    }

    /**
     * @return array<string, object>
     */
    private function aggregateRows(Builder $query): array
    {
        $rows = [];

        foreach ($query->get() as $row) {
            $rows[(string) $row->station] = $row;
        }

        return $rows;
    }

    /**
     * @return array{orders: int, avg_minutes: float, late: int, late_percent: float}
     */
    private function shape(?object $row): array
    {
        $data = (array) $row;
        $orders = (int) ($data['orders'] ?? 0);
        $late = (int) ($data['late'] ?? 0);

        return [
            'orders' => $orders,
            'avg_minutes' => $orders > 0 ? round((float) ($data['avg_seconds'] ?? 0) / 60, 1) : 0.0,
            'late' => $late,
            'late_percent' => $orders > 0 ? round($late / $orders * 100, 1) : 0.0,
        ];
    }
}

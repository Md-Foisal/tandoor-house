<?php

namespace App\Reports\Sections;

use App\Reports\ReportRange;
use App\Reports\ReportTable;
use App\Reports\SalesQuery;

final class Tips
{
    /**
     * @return array<string, mixed>
     */
    public function data(ReportRange $range): array
    {
        $series = SalesQuery::series($range, SalesQuery::hourly($range));
        $weekly = $range->weekly();

        $rows = array_map(fn (array $row) => [
            'key' => $row['key'],
            'label' => $row['label'],
            'service' => $row['service'],
            'tips' => $row['tips'],
            'total' => $row['service'] + $row['tips'],
        ], $series);

        $totals = [
            'key' => __('staff_reports.total'),
            'service' => array_sum(array_column($rows, 'service')),
            'tips' => array_sum(array_column($rows, 'tips')),
            'total' => array_sum(array_column($rows, 'total')),
        ];

        $table = ReportTable::make('tips', __('staff_reports.tips.title'), [
            'key' => 'date|'.__($weekly ? 'staff_reports.columns.week_starting' : 'staff_reports.columns.day'),
            'service' => 'money|'.__('staff_reports.columns.service_charge'),
            'tips' => 'money|'.__('staff_reports.columns.tips'),
            'total' => 'money|'.__('staff_reports.columns.total'),
        ], array_map(fn (array $row) => array_diff_key($row, ['label' => 1]), $rows), array_diff_key($totals, []), __('staff_reports.tips.note'));

        return [
            'granularity' => $weekly ? 'week' : 'day',
            'days' => $rows,
            'totals' => $totals,
            'tables' => [$table->toArray()],
        ];
    }
}

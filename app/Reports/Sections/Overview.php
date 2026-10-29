<?php

namespace App\Reports\Sections;

use App\Reports\ReportRange;
use App\Reports\ReportTable;
use App\Reports\SalesQuery;

final class Overview
{
    /** Metrics where a rise is good news, a fall is bad, or neither. */
    private const DIRECTION = [
        'sales' => 'up', 'gross' => 'up', 'net_of_vat' => 'up', 'orders' => 'up', 'aov' => 'up',
        'discounts' => 'down', 'refunds' => 'down',
    ];

    private const MONEY = ['sales', 'gross', 'net_of_vat', 'vat', 'discounts', 'delivery', 'service', 'tips', 'refunds', 'aov'];

    private const ORDER = ['sales', 'gross', 'net_of_vat', 'vat', 'discounts', 'delivery', 'service', 'tips', 'refunds', 'orders', 'aov'];

    /**
     * @return array<string, mixed>
     */
    public function data(ReportRange $range): array
    {
        $current = SalesQuery::totals($range);
        $previous = SalesQuery::totals($range, previous: true);

        $metrics = [];
        $rows = [];

        foreach (self::ORDER as $key) {
            $format = in_array($key, self::MONEY, true) ? 'money' : 'int';
            $change = self::change($current[$key], $previous[$key]);
            $metric = [
                'key' => $key,
                'label' => __("staff_reports.overview.metrics.{$key}"),
                'hint' => __("staff_reports.overview.hints.{$key}"),
                'format' => $format,
                'value' => $current[$key],
                'previous' => $previous[$key],
                'change' => $change,
                'direction' => self::DIRECTION[$key] ?? 'none',
            ];
            $metrics[] = $metric;
            $rows[] = [
                'label' => $metric['label'],
                'value' => $metric['value'],
                'previous' => $metric['previous'],
                'change' => $change,
                '_format' => $format,
            ];
        }

        $table = ReportTable::make('overview', __('staff_reports.overview.title'), [
            'label' => 'text|'.__('staff_reports.columns.measure'),
            'value' => 'auto|'.__('staff_reports.columns.this_period'),
            'previous' => 'auto|'.__('staff_reports.columns.previous_period'),
            'change' => 'change|'.__('staff_reports.columns.change'),
        ], $rows, note: __('staff_reports.overview.compared_with', ['period' => $range->previousLabel()]));

        return [
            'metrics' => $metrics,
            'previous_label' => $range->previousLabel(),
            'tables' => [$table->toArray()],
        ];
    }

    private static function change(int $value, int $previous): ?float
    {
        if ($previous === 0) {
            return $value === 0 ? 0.0 : null;
        }

        return round(($value - $previous) / $previous * 100, 1);
    }
}

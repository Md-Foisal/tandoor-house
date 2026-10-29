<?php

namespace App\Reports\Sections;

use App\Enums\OrderChannel;
use App\Enums\PaymentMethod;
use App\Reports\HourlySales;
use App\Reports\OrderScope;
use App\Reports\ReportRange;
use App\Reports\ReportTable;
use App\Reports\SalesQuery;
use Illuminate\Support\Collection;

final class Breakdown
{
    private const LUNCH = [12, 14];

    private const DINNER = [17, 21];

    /**
     * @return array<string, mixed>
     */
    public function data(ReportRange $range): array
    {
        $hourly = SalesQuery::hourly($range);
        $series = SalesQuery::series($range, $hourly);
        $hours = $this->hours($hourly);
        $channels = $this->channels($hourly);
        $payments = $this->payments($range);
        $weekly = $range->weekly();

        $daily = ReportTable::make(
            'daily',
            __($weekly ? 'staff_reports.breakdown.by_week' : 'staff_reports.breakdown.by_day'),
            [
                'key' => 'date|'.__($weekly ? 'staff_reports.columns.week_starting' : 'staff_reports.columns.day'),
                'orders' => 'int|'.__('staff_reports.columns.orders'),
                'sales' => 'money|'.__('staff_reports.columns.sales'),
            ],
            array_map(fn (array $row) => ['key' => $row['key'], 'orders' => $row['orders'], 'sales' => $row['sales']], $series),
            ['key' => __('staff_reports.total'), 'orders' => array_sum(array_column($series, 'orders')), 'sales' => array_sum(array_column($series, 'sales'))],
        );

        $byHour = ReportTable::make(
            'hourly',
            __('staff_reports.breakdown.by_hour'),
            [
                'label' => 'text|'.__('staff_reports.columns.hour'),
                'period' => 'text|'.__('staff_reports.columns.service'),
                'orders' => 'int|'.__('staff_reports.columns.orders'),
                'sales' => 'money|'.__('staff_reports.columns.sales'),
            ],
            array_map(fn (array $row) => [...$row, 'period' => $row['period'] === 'other' ? '' : __("staff_reports.breakdown.{$row['period']}")], $hours),
            note: __('staff_reports.breakdown.hours_note'),
        );

        $byChannel = ReportTable::make('channels', __('staff_reports.breakdown.by_channel'), [
            'label' => 'text|'.__('staff_reports.columns.channel'),
            'orders' => 'int|'.__('staff_reports.columns.orders'),
            'sales' => 'money|'.__('staff_reports.columns.sales'),
            'share' => 'percent|'.__('staff_reports.columns.share'),
        ], $channels);

        $byPayment = ReportTable::make('payments', __('staff_reports.breakdown.payment_mix'), [
            'label' => 'text|'.__('staff_reports.columns.method'),
            'orders' => 'int|'.__('staff_reports.columns.orders'),
            'sales' => 'money|'.__('staff_reports.columns.sales'),
            'share' => 'percent|'.__('staff_reports.columns.share'),
        ], $payments);

        return [
            'granularity' => $weekly ? 'week' : 'day',
            'daily' => array_map(fn (array $row) => ['key' => $row['key'], 'label' => $row['label'], 'orders' => $row['orders'], 'sales' => $row['sales']], $series),
            'hours' => $hours,
            'channels' => $channels,
            'payments' => $payments,
            'tables' => [$daily->toArray(), $byHour->toArray(), $byChannel->toArray(), $byPayment->toArray()],
        ];
    }

    /**
     * Every hour from the first to the last with sales, widened to cover a normal service.
     *
     * @param  Collection<int, HourlySales>  $hourly
     * @return list<array{hour: int, label: string, period: string, orders: int, sales: int}>
     */
    private function hours(Collection $hourly): array
    {
        $byHour = [];

        foreach ($hourly as $row) {
            $byHour[$row->hour] ??= ['orders' => 0, 'sales' => 0];
            $byHour[$row->hour]['orders'] += $row->orders;
            $byHour[$row->hour]['sales'] += $row->sales;
        }

        $first = min([self::LUNCH[0], ...array_keys($byHour)]);
        $last = max([self::DINNER[1], ...array_keys($byHour)]);
        $rows = [];

        for ($hour = $first; $hour <= $last; $hour++) {
            $rows[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'period' => match (true) {
                    $hour >= self::LUNCH[0] && $hour <= self::LUNCH[1] => 'lunch',
                    $hour >= self::DINNER[0] && $hour <= self::DINNER[1] => 'dinner',
                    default => 'other',
                },
                'orders' => $byHour[$hour]['orders'] ?? 0,
                'sales' => $byHour[$hour]['sales'] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, HourlySales>  $hourly
     * @return list<array{key: string, label: string, orders: int, sales: int, share: float}>
     */
    private function channels(Collection $hourly): array
    {
        $total = (int) $hourly->sum('sales');

        return array_map(function (OrderChannel $channel) use ($hourly, $total) {
            $sales = (int) $hourly->where('channel', $channel->value)->sum('sales');

            return [
                'key' => $channel->value,
                'label' => __("staff.channel.{$channel->value}"),
                'orders' => (int) $hourly->where('channel', $channel->value)->sum('orders'),
                'sales' => $sales,
                'share' => $total > 0 ? round($sales / $total * 100, 1) : 0.0,
            ];
        }, OrderChannel::cases());
    }

    /**
     * @return list<array{key: string, label: string, orders: int, sales: int, share: float}>
     */
    private function payments(ReportRange $range): array
    {
        $found = OrderScope::orders($range)
            ->selectRaw('orders.payment_method as method, count(*) as orders, coalesce(sum('.SalesQuery::SALES.'), 0) as sales')
            ->groupBy('orders.payment_method')
            ->get()
            ->keyBy('method');

        $total = (int) $found->sum('sales');

        return array_map(function (PaymentMethod $method) use ($found, $total) {
            $sales = (int) ($found[$method->value]->sales ?? 0);

            return [
                'key' => $method->value,
                'label' => __("staff_reports.payment_methods.{$method->value}"),
                'orders' => (int) ($found[$method->value]->orders ?? 0),
                'sales' => $sales,
                'share' => $total > 0 ? round($sales / $total * 100, 1) : 0.0,
            ];
        }, PaymentMethod::cases());
    }
}

<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate order money for a period. "Sales" is menu sales after discounts
 * plus delivery fees, VAT included; tips and the service charge are reported
 * separately because they belong to staff.
 */
final class SalesQuery
{
    public const SALES = 'subtotal - discount + delivery_fee';

    /**
     * @return array{orders: int, gross: int, discounts: int, delivery: int, vat: int, service: int, tips: int, total: int, refunds: int, sales: int, net_of_vat: int, aov: int}
     */
    public static function totals(ReportRange $range, bool $previous = false): array
    {
        $row = OrderScope::orders($range, $previous)->selectRaw(
            'count(*) as orders, coalesce(sum(subtotal), 0) as gross, coalesce(sum(discount), 0) as discounts, '
            .'coalesce(sum(delivery_fee), 0) as delivery, coalesce(sum(vat_total), 0) as vat, '
            .'coalesce(sum(service_charge), 0) as service, coalesce(sum(tip), 0) as tips, coalesce(sum(total), 0) as total',
        )->first();

        $refunds = (int) DB::table('payments')
            ->joinSub(OrderScope::orders($range, $previous)->select('orders.id'), 'scoped', 'scoped.id', '=', 'payments.order_id')
            ->sum('payments.refunded');

        $orders = (int) $row->orders;
        $gross = (int) $row->gross;
        $discounts = (int) $row->discounts;
        $delivery = (int) $row->delivery;
        $vat = (int) $row->vat;
        $beforeRefunds = $gross - $discounts + $delivery;

        return [
            'orders' => $orders,
            'gross' => $gross,
            'discounts' => $discounts,
            'delivery' => $delivery,
            'vat' => $vat,
            'service' => (int) $row->service,
            'tips' => (int) $row->tips,
            'total' => (int) $row->total,
            'refunds' => $refunds,
            'sales' => $beforeRefunds - $refunds,
            'net_of_vat' => $gross - $discounts - $vat,
            'aov' => $orders > 0 ? (int) round($beforeRefunds / $orders) : 0,
        ];
    }

    /**
     * One row per local hour and channel.
     *
     * @return Collection<int, HourlySales>
     */
    public static function hourly(ReportRange $range): Collection
    {
        return OrderScope::orders($range)
            ->selectRaw(
                'substr(orders.placed_at, 1, 13) as bucket, orders.channel as channel, count(*) as orders, '
                .'coalesce(sum('.self::SALES.'), 0) as sales, coalesce(sum(service_charge), 0) as service, coalesce(sum(tip), 0) as tips',
            )
            ->groupByRaw('substr(orders.placed_at, 1, 13), orders.channel')
            ->get()
            ->map(fn ($row) => new HourlySales(
                date: substr((string) $row->bucket, 0, 10),
                hour: (int) substr((string) $row->bucket, 11, 2),
                channel: (string) $row->channel,
                orders: (int) $row->orders,
                sales: (int) $row->sales,
                service: (int) $row->service,
                tips: (int) $row->tips,
            ));
    }

    /**
     * Days (or Monday-start weeks for long ranges) with zero-filled gaps.
     *
     * @param  Collection<int, HourlySales>  $hourly
     * @return list<array{key: string, label: string, orders: int, sales: int, service: int, tips: int}>
     */
    public static function series(ReportRange $range, Collection $hourly): array
    {
        $weekly = $range->weekly();
        $rows = [];
        $cursor = $range->start;

        while ($cursor->lessThan($range->end)) {
            $key = $weekly ? $cursor->startOfWeek()->format('Y-m-d') : $cursor->format('Y-m-d');

            $rows[$key] ??= [
                'key' => $key,
                'label' => $weekly ? __('staff_reports.week_of', ['date' => $cursor->startOfWeek()->format('j M')]) : $cursor->format('D j M'),
                'orders' => 0, 'sales' => 0, 'service' => 0, 'tips' => 0,
            ];
            $cursor = $cursor->addDay();
        }

        foreach ($hourly as $row) {
            $day = CarbonImmutable::parse($row->date, (string) config('app.timezone'));
            $key = $weekly ? $day->startOfWeek()->format('Y-m-d') : $row->date;

            if (! isset($rows[$key])) {
                continue;
            }

            $rows[$key]['orders'] += $row->orders;
            $rows[$key]['sales'] += $row->sales;
            $rows[$key]['service'] += $row->service;
            $rows[$key]['tips'] += $row->tips;
        }

        return array_values($rows);
    }
}

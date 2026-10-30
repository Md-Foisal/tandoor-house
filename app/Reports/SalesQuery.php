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
     * @return array{orders: int, gross: int, discounts: int, delivery: int, vat: int, service: int, tips: int, total: int, refunds: int, tip_refunds: int, sales: int, net_of_vat: int, aov: int}
     */
    public static function totals(ReportRange $range, bool $previous = false): array
    {
        $row = OrderScope::orders($range, $previous)->selectRaw(
            'count(*) as orders, coalesce(sum(subtotal), 0) as gross, coalesce(sum(discount), 0) as discounts, '
            .'coalesce(sum(delivery_fee), 0) as delivery, coalesce(sum(vat_total), 0) as vat, '
            .'coalesce(sum(service_charge), 0) as service, coalesce(sum(tip), 0) as tips, coalesce(sum(total), 0) as total',
        )->first();

        $refunds = self::refunds($range, $previous);

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
            'refunds' => $refunds['sales'],
            'tip_refunds' => $refunds['tips'],
            'sales' => $beforeRefunds - $refunds['sales'],
            'net_of_vat' => $gross - $discounts - $vat,
            'aov' => $orders > 0 ? (int) round($beforeRefunds / $orders) : 0,
        ];
    }

    /**
     * Money given back in the period, whenever the order was placed. A refund of an order
     * is split in the proportion the order was made of: the food, drink and delivery share
     * reduces sales, the tip and service charge share is reported on its own. Each refund
     * is dated by its entry in the payment's refund log, or by the payment's last update
     * when the log does not account for all of it.
     *
     * @return array{sales: int, tips: int}
     */
    public static function refunds(ReportRange $range, bool $previous = false): array
    {
        $start = $previous ? $range->previousStart : $range->start;
        $end = $previous ? $range->previousEnd : $range->end;
        $timezone = (string) config('app.timezone');
        $inPeriod = fn (CarbonImmutable $at) => $at->greaterThanOrEqualTo($start) && $at->lessThan($end);

        $payments = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->whereIn('orders.status', OrderScope::statuses())
            ->when($range->channel, fn ($q) => $q->where('orders.channel', $range->channel?->value))
            ->where('payments.refunded', '>', 0)
            ->where('payments.updated_at', '>=', $start->format('Y-m-d H:i:s'))
            ->select('payments.refunded', 'payments.meta', 'payments.updated_at', 'orders.subtotal', 'orders.discount', 'orders.delivery_fee', 'orders.total')
            ->cursor();

        $sales = 0;
        $tips = 0;

        foreach ($payments as $payment) {
            $log = json_decode((string) $payment->meta, true);
            $entries = is_array($log) && is_array($log['refunds'] ?? null) ? $log['refunds'] : [];
            $counted = [];
            $logged = 0;

            foreach ($entries as $entry) {
                $amount = (int) ($entry['amount'] ?? 0);
                $logged += $amount;

                if (isset($entry['at']) && $inPeriod(CarbonImmutable::parse((string) $entry['at'])->setTimezone($timezone))) {
                    $counted[] = $amount;
                }
            }

            $unlogged = (int) $payment->refunded - $logged;

            if ($unlogged > 0 && $inPeriod(CarbonImmutable::parse((string) $payment->updated_at, $timezone))) {
                $counted[] = $unlogged;
            }

            $menuAndDelivery = (int) $payment->subtotal - (int) $payment->discount + (int) $payment->delivery_fee;

            foreach ($counted as $amount) {
                $share = (int) $payment->total > 0 ? min($amount, (int) round($amount * $menuAndDelivery / (int) $payment->total)) : $amount;
                $sales += $share;
                $tips += $amount - $share;
            }
        }

        return ['sales' => $sales, 'tips' => $tips];
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
                OrderScope::hourBucket().' as bucket, orders.channel as channel, count(*) as orders, '
                .'coalesce(sum('.self::SALES.'), 0) as sales, coalesce(sum(service_charge), 0) as service, coalesce(sum(tip), 0) as tips',
            )
            ->groupByRaw(OrderScope::hourBucket().', orders.channel')
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

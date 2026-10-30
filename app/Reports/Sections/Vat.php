<?php

namespace App\Reports\Sections;

use App\Reports\OrderScope;
use App\Reports\ReportRange;
use App\Reports\ReportTable;
use App\Reports\SalesQuery;
use Illuminate\Support\Facades\DB;

final class Vat
{
    /**
     * VAT inside food and drink lines, by rate. Menu prices include VAT, and a
     * discount reduces every line in proportion, as the till does.
     *
     * @return array<string, mixed>
     */
    public function data(ReportRange $range): array
    {
        $found = DB::table('order_lines')
            ->joinSub(
                OrderScope::orders($range)->where('orders.subtotal', '>', 0)->select('orders.id', 'orders.subtotal', 'orders.discount'),
                'scoped',
                'scoped.id',
                '=',
                'order_lines.order_id',
            )
            ->selectRaw('order_lines.vat_rate as rate, sum(order_lines.unit_price * order_lines.quantity * (scoped.subtotal - scoped.discount) * 1.0 / scoped.subtotal) as gross')
            ->groupBy('order_lines.vat_rate')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->rate => (int) round((float) $row->gross)]);

        $rates = collect([2000, 0])->merge($found->keys())->unique()->sortDesc()->values();

        $rows = $rates->map(function (int $rate) use ($found) {
            $gross = $found[$rate] ?? 0;
            $vat = (int) round($gross * $rate / (10000 + $rate));

            return ['rate' => $rate, 'net' => $gross - $vat, 'vat' => $vat, 'gross' => $gross];
        })->all();

        $totals = [
            'rate' => null,
            'label' => __('staff_reports.total'),
            'net' => array_sum(array_column($rows, 'net')),
            'vat' => array_sum(array_column($rows, 'vat')),
            'gross' => array_sum(array_column($rows, 'gross')),
        ];

        $sales = SalesQuery::totals($range);

        $vatTable = ReportTable::make('vat', __('staff_reports.vat.title'), [
            'rate' => 'rate|'.__('staff_reports.columns.vat_rate'),
            'net' => 'money|'.__('staff_reports.columns.net'),
            'vat' => 'money|'.__('staff_reports.columns.vat'),
            'gross' => 'money|'.__('staff_reports.columns.gross'),
        ], $rows, ['rate' => __('staff_reports.total'), ...array_diff_key($totals, ['rate' => 1, 'label' => 1])], __('staff_reports.vat.note'));

        $outside = ReportTable::make('vat_outside', __('staff_reports.vat.outside_title'), [
            'label' => 'text|'.__('staff_reports.columns.measure'),
            'amount' => 'money|'.__('staff_reports.columns.amount'),
        ], [
            ['label' => __('staff_reports.overview.metrics.delivery'), 'amount' => $sales['delivery']],
            ['label' => __('staff_reports.overview.metrics.service'), 'amount' => $sales['service']],
            ['label' => __('staff_reports.overview.metrics.tips'), 'amount' => $sales['tips']],
            ['label' => __('staff_reports.overview.metrics.refunds'), 'amount' => $sales['refunds']],
            ['label' => __('staff_reports.overview.metrics.tip_refunds'), 'amount' => $sales['tip_refunds']],
        ], note: __('staff_reports.vat.outside_note'));

        return [
            'rows' => $rows,
            'totals' => $totals,
            'period' => $range->label(),
            'orders' => $sales['orders'],
            'tables' => [$vatTable->toArray(), $outside->toArray()],
        ];
    }
}

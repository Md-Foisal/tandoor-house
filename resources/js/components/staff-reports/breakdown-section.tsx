import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { BarChart, type Band } from './bar-chart';
import { formatDay } from './format';
import { EmptyPeriod, ReportCard } from './report-card';
import { TableDisclosure } from './report-table';
import { ShareBar, type ShareItem } from './share-bar';
import type { BreakdownData, ShareRow } from './types';

function shareItems(rows: ShareRow[]): ShareItem[] {
    return rows.map((row) => ({
        key: row.key,
        label: row.label,
        value: row.sales,
        share: row.share,
        valueLabel: formatMoney(row.sales, { compact: true }),
    }));
}

export function BreakdownSection({
    data,
    exportHref,
}: {
    data: BreakdownData;
    exportHref: string;
}) {
    const t = useT();
    const money = (pence: number) => formatMoney(pence, { compact: true });
    const weekly = data.granularity === 'week';
    const [daily, hourly, channels, payments] = data.tables;
    const total = data.daily.reduce((sum, row) => sum + row.sales, 0);

    const peakDay = data.daily.reduce((a, b) => (b.sales > a.sales ? b : a));
    const peakHour = data.hours.reduce((a, b) => (b.sales > a.sales ? b : a));

    const bands: Band[] = (['lunch', 'dinner'] as const).flatMap((period) => {
        const indexes = data.hours
            .map((row, index) => (row.period === period ? index : -1))
            .filter((index) => index >= 0);

        return indexes.length
            ? [
                  {
                      from: indexes[0],
                      to: indexes[indexes.length - 1],
                      label: t(`staff_reports.breakdown.${period}`),
                  },
              ]
            : [];
    });

    return (
        <ReportCard
            id="breakdown"
            title={t('staff_reports.sections.breakdown')}
            description={t('staff_reports.breakdown.sales_note')}
            exportHref={exportHref}
        >
            {total === 0 ? (
                <EmptyPeriod />
            ) : (
                <div className="grid gap-8 xl:grid-cols-2 [&>*]:min-w-0">
                    <div className="flex flex-col gap-3">
                        <h3 className="text-title font-semibold">
                            {daily.title}
                        </h3>
                        <BarChart
                            bars={data.daily.map((row) => ({
                                key: row.key,
                                label: row.label,
                                values: [row.sales],
                            }))}
                            series={[{ key: 'sales', label: daily.title }]}
                            formatValue={money}
                            labelPeak
                            ariaLabel={t(
                                weekly
                                    ? 'staff_reports.breakdown.week_chart'
                                    : 'staff_reports.breakdown.day_chart',
                                {
                                    label: weekly
                                        ? formatDay(peakDay.key)
                                        : peakDay.label,
                                    value: formatMoney(peakDay.sales),
                                },
                            )}
                        />
                        <TableDisclosure table={daily} />
                    </div>

                    <div className="flex flex-col gap-3">
                        <h3 className="text-title font-semibold">
                            {hourly.title}
                        </h3>
                        <BarChart
                            bars={data.hours.map((row) => ({
                                key: String(row.hour),
                                label: row.label.slice(0, 2),
                                values: [row.sales],
                            }))}
                            series={[{ key: 'sales', label: hourly.title }]}
                            bands={bands}
                            formatValue={money}
                            labelPeak
                            ariaLabel={t('staff_reports.breakdown.hour_chart', {
                                label: peakHour.label,
                                value: formatMoney(peakHour.sales),
                            })}
                        />
                        <TableDisclosure table={hourly} />
                    </div>

                    <div className="flex flex-col gap-3">
                        <h3 className="text-title font-semibold">
                            {channels.title}
                        </h3>
                        <ShareBar
                            items={shareItems(data.channels)}
                            ariaLabel={t(
                                'staff_reports.breakdown.channel_chart',
                            )}
                        />
                        <TableDisclosure table={channels} />
                    </div>

                    <div className="flex flex-col gap-3">
                        <h3 className="text-title font-semibold">
                            {payments.title}
                        </h3>
                        <ShareBar
                            items={shareItems(data.payments)}
                            ariaLabel={t(
                                'staff_reports.breakdown.payment_chart',
                            )}
                        />
                        <TableDisclosure table={payments} />
                    </div>
                </div>
            )}
        </ReportCard>
    );
}

import { StatTile } from '@/components/staff/stat-tile';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { Delta, deltaTone } from './delta';
import { HBarChart } from './hbar-chart';
import { ReportCard } from './report-card';
import { ReportTable } from './report-table';
import type { OverviewData } from './types';

export function OverviewSection({
    data,
    exportHref,
}: {
    data: OverviewData;
    exportHref: string;
}) {
    const t = useT();
    const find = (key: string) => data.metrics.find((m) => m.key === key)!;
    const sales = find('sales');
    const orders = find('orders');
    const aov = find('aov');
    const tile = (metric: typeof orders) => (
        <StatTile
            key={metric.key}
            label={metric.label}
            value={
                metric.format === 'money'
                    ? formatMoney(metric.value)
                    : metric.value.toLocaleString('en-GB')
            }
            tone={deltaTone(metric)}
            hint={<Delta metric={metric} />}
        />
    );

    return (
        <ReportCard
            id="overview"
            title={t('staff_reports.sections.overview')}
            description={t('staff_reports.basis')}
            exportHref={exportHref}
        >
            <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr] [&>*]:min-w-0">
                <div className="flex min-w-0 flex-col gap-4 rounded-lg border border-line bg-paper p-4 md:p-5">
                    <div>
                        <p className="text-label text-ink-muted">
                            {sales.label}
                        </p>
                        <p className="font-display text-display-lg tabular-nums">
                            {formatMoney(sales.value)}
                        </p>
                        <Delta metric={sales} />
                    </div>
                    <HBarChart
                        ariaLabel={t('staff_reports.overview.chart_label', {
                            current: formatMoney(sales.value),
                            previous: formatMoney(sales.previous),
                        })}
                        rows={[
                            {
                                key: 'now',
                                label: t('staff_reports.overview.this_period'),
                                value: sales.value,
                                valueLabel: formatMoney(sales.value, {
                                    compact: true,
                                }),
                                slot: 0,
                            },
                            {
                                key: 'before',
                                label: t(
                                    'staff_reports.overview.previous_period',
                                ),
                                value: sales.previous,
                                valueLabel: formatMoney(sales.previous, {
                                    compact: true,
                                }),
                                slot: 3,
                            },
                        ]}
                    />
                    <p className="text-sm text-ink-muted">{sales.hint}</p>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 [&>*]:min-w-0">
                    {tile(orders)}
                    {tile(aov)}
                </div>
            </div>
            <ReportTable table={data.tables[0]} />
        </ReportCard>
    );
}

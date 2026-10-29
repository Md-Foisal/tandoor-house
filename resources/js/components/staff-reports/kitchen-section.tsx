import { StatTile } from '@/components/staff/stat-tile';
import { choice, useT } from '@/lib/i18n';
import { HBarChart } from './hbar-chart';
import { formatPercent } from './format';
import { ReportCard } from './report-card';
import { ReportTable } from './report-table';
import type { KitchenData } from './types';

export function KitchenSection({
    data,
    exportHref,
}: {
    data: KitchenData;
    exportHref: string;
}) {
    const t = useT();
    const { overall, stations } = data;
    const minutes = (value: number) =>
        t('staff_reports.kitchen.minutes', { value: value.toFixed(1) });

    return (
        <ReportCard
            id="kitchen"
            title={t('staff_reports.sections.kitchen')}
            description={t('staff_reports.kitchen.note')}
            exportHref={exportHref}
        >
            {overall.orders === 0 ? (
                <p className="rounded-md bg-clay px-4 py-6 text-ink-muted">
                    {t('staff_reports.kitchen.none')}
                </p>
            ) : (
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] [&>*]:min-w-0">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 [&>*]:min-w-0">
                        <StatTile
                            label={t('staff_reports.kitchen.avg_prep')}
                            value={minutes(overall.avg_minutes)}
                            hint={t('staff_reports.kitchen.of_orders', {
                                count: overall.orders,
                            })}
                        />
                        <StatTile
                            label={t('staff_reports.kitchen.late')}
                            value={formatPercent(overall.late_percent)}
                            tone={overall.late_percent > 20 ? 'bad' : 'plain'}
                            hint={choice(
                                t('staff_reports.kitchen.late_count', {
                                    count: overall.late,
                                }),
                                overall.late,
                            )}
                        />
                    </div>
                    <HBarChart
                        ariaLabel={t('staff_reports.kitchen.chart')}
                        rows={stations.map((station, index) => ({
                            key: station.key,
                            label: station.label,
                            value: station.avg_minutes,
                            valueLabel: `${station.avg_minutes.toFixed(1)} min`,
                            slot: index % 5,
                        }))}
                    />
                </div>
            )}
            <ReportTable table={{ ...data.tables[0], note: null }} />
        </ReportCard>
    );
}

import { Info } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { BarChart } from './bar-chart';
import { EmptyPeriod, ReportCard } from './report-card';
import { ReportTable } from './report-table';
import type { TipsData } from './types';

export function TipsSection({
    data,
    exportHref,
}: {
    data: TipsData;
    exportHref: string;
}) {
    const t = useT();
    const none = data.totals.total === 0;

    return (
        <ReportCard
            id="tips"
            title={t('staff_reports.sections.tips')}
            exportHref={exportHref}
        >
            <p className="flex items-start gap-2 rounded-md bg-clay px-4 py-3 text-sm text-ink">
                <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                {t('staff_reports.tips.note')}
            </p>
            {none ? (
                <EmptyPeriod />
            ) : (
                <BarChart
                    bars={data.days.map((day) => ({
                        key: day.key,
                        label: day.label,
                        values: [day.service, day.tips],
                    }))}
                    series={[
                        {
                            key: 'service',
                            label: t('staff_reports.columns.service_charge'),
                        },
                        { key: 'tips', label: t('staff_reports.columns.tips') },
                    ]}
                    formatValue={(pence) =>
                        formatMoney(pence, { compact: true })
                    }
                    ariaLabel={t('staff_reports.tips.chart')}
                />
            )}
            <ReportTable table={data.tables[0]} />
        </ReportCard>
    );
}

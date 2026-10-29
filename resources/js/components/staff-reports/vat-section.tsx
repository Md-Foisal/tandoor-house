import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { ReportCard } from './report-card';
import { ReportTable } from './report-table';
import { ShareBar } from './share-bar';
import type { VatData } from './types';

export function VatSection({
    data,
    exportHref,
}: {
    data: VatData;
    exportHref: string;
}) {
    const t = useT();
    const [vat, outside] = data.tables;

    return (
        <ReportCard
            id="vat"
            title={t('staff_reports.sections.vat')}
            description={t('staff_reports.vat.period', {
                period: data.period,
            })}
            exportHref={exportHref}
        >
            <ShareBar
                ariaLabel={t('staff_reports.vat.chart')}
                items={data.rows.map((row) => ({
                    key: String(row.rate),
                    label: t('staff_reports.vat.rate_label', {
                        rate: row.rate / 100,
                    }),
                    value: row.gross,
                    share:
                        data.totals.gross > 0
                            ? Math.round(
                                  (row.gross / data.totals.gross) * 1000,
                              ) / 10
                            : 0,
                    valueLabel: formatMoney(row.gross, { compact: true }),
                }))}
            />
            <ReportTable
                table={{
                    ...vat,
                    note: t('staff_reports.vat.note', { orders: data.orders }),
                }}
            />
            <ReportTable table={outside} />
            <p className="text-sm text-ink-muted">
                {t('staff_reports.vat.quarter_tip')}
            </p>
        </ReportCard>
    );
}

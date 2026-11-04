import { useState } from 'react';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { HBarChart } from './hbar-chart';
import { EmptyPeriod, ReportCard } from './report-card';
import { TableDisclosure } from './report-table';
import { ShareBar } from './share-bar';
import type { DishRow, ItemsData } from './types';

type By = 'quantity' | 'revenue';

function dishRows(rows: DishRow[], by: By) {
    return rows.map((row, index) => ({
        key: `${index}-${row.name}`,
        label: row.name,
        value: row[by],
        valueLabel:
            by === 'revenue'
                ? formatMoney(row.revenue, { compact: true })
                : row.quantity.toLocaleString('en-GB'),
        slot: 0,
    }));
}

export function ItemsSection({
    data,
    exportHref,
}: {
    data: ItemsData;
    exportHref: string;
}) {
    const t = useT();
    const [by, setBy] = useState<By>('quantity');
    const table = (key: string) => data.tables.find((x) => x.key === key)!;
    const top = by === 'quantity' ? data.top_quantity : data.top_revenue;
    const slow = by === 'quantity' ? data.slow_quantity : data.slow_revenue;
    const byWord = t(`staff_reports.items.by.${by}`);

    // Four parts and "Other", never a cycled colour.
    const total = data.categories.reduce((sum, c) => sum + c.revenue, 0);
    const head = data.categories.slice(0, 4);
    const rest = data.categories.slice(4);
    const categoryItems = [
        ...head,
        ...(rest.length
            ? [
                  {
                      name: t('staff_reports.items.other'),
                      quantity: 0,
                      revenue: rest.reduce((s, c) => s + c.revenue, 0),
                      share: rest.reduce((s, c) => s + c.share, 0),
                  },
              ]
            : []),
    ].map((c) => ({
        key: c.name,
        label: c.name,
        value: c.revenue,
        share: c.share,
        valueLabel: formatMoney(c.revenue, { compact: true }),
    }));

    return (
        <ReportCard
            id="items"
            title={t('staff_reports.sections.items')}
            description={t('staff_reports.items.revenue_note')}
            exportHref={exportHref}
        >
            {total === 0 && top.length === 0 ? (
                <EmptyPeriod />
            ) : (
                <>
                    <Tabs
                        value={by}
                        onValueChange={(value) => setBy(value as By)}
                        className="gap-6"
                    >
                        <TabsList aria-label={t('staff_reports.items.tabs')}>
                            <TabsTrigger
                                value="quantity"
                                aria-controls={undefined}
                                className="min-h-11"
                            >
                                {t('staff_reports.items.by_quantity')}
                            </TabsTrigger>
                            <TabsTrigger
                                value="revenue"
                                aria-controls={undefined}
                                className="min-h-11"
                            >
                                {t('staff_reports.items.by_revenue')}
                            </TabsTrigger>
                        </TabsList>

                        <div className="grid gap-8 xl:grid-cols-2 [&>*]:min-w-0">
                            <div className="flex flex-col gap-3">
                                <h3 className="text-title font-semibold">
                                    {t('staff_reports.items.top')}
                                </h3>
                                <HBarChart
                                    rows={dishRows(top, by)}
                                    ariaLabel={t(
                                        'staff_reports.items.top_chart',
                                        { by: byWord },
                                    )}
                                />
                                <TableDisclosure table={table(`top_${by}`)} />
                            </div>
                            <div className="flex flex-col gap-3">
                                <h3 className="text-title font-semibold">
                                    {t('staff_reports.items.slow')}
                                </h3>
                                <HBarChart
                                    rows={dishRows(slow, by).map((row) => ({
                                        ...row,
                                        slot: 3,
                                    }))}
                                    ariaLabel={t(
                                        'staff_reports.items.slow_chart',
                                        { by: byWord },
                                    )}
                                />
                                <TableDisclosure table={table(`slow_${by}`)} />
                            </div>
                        </div>
                    </Tabs>

                    <div className="flex flex-col gap-3">
                        <h3 className="text-title font-semibold">
                            {t('staff_reports.items.categories')}
                        </h3>
                        <ShareBar
                            items={categoryItems}
                            ariaLabel={t('staff_reports.items.category_chart')}
                        />
                        <TableDisclosure table={table('categories')} />
                    </div>
                </>
            )}
        </ReportCard>
    );
}

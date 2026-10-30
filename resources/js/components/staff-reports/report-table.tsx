import type { ReactNode } from 'react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { formatCell } from './format';
import type { ColumnType, ReportTableData } from './types';

const numeric: ColumnType[] = [
    'int',
    'money',
    'percent',
    'change',
    'minutes',
    'auto',
];

/**
 * The data behind a chart, as a real table. On a phone it scrolls sideways
 * with the first column pinned, so it stays a table for screen readers.
 */
export function ReportTable({
    table,
    className,
}: {
    table: ReportTableData;
    className?: string;
}) {
    const t = useT();

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <div
                role="region"
                tabIndex={0}
                aria-label={t('staff_reports.table.region', {
                    title: table.title,
                })}
                className="overflow-x-auto rounded-md border border-line outline-hidden focus-visible:outline-2 focus-visible:outline-ember"
            >
                <table className="w-full min-w-max border-collapse text-sm">
                    <caption className="sr-only">{table.title}</caption>
                    <thead>
                        <tr className="bg-clay text-left">
                            {table.columns.map((column, index) => (
                                <th
                                    key={column.key}
                                    scope="col"
                                    className={cn(
                                        'px-3 py-2 text-label font-semibold text-ink-muted',
                                        numeric.includes(column.type) &&
                                            'text-right',
                                        index === 0 && 'sticky left-0 bg-clay',
                                    )}
                                >
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {table.rows.map((row, rowIndex) => (
                            <tr key={rowIndex} className="border-t border-line">
                                {table.columns.map((column, index) =>
                                    index === 0 ? (
                                        <th
                                            key={column.key}
                                            scope="row"
                                            className="sticky left-0 bg-surface px-3 py-2 text-left font-medium text-ink"
                                        >
                                            {formatCell(
                                                row[column.key],
                                                column.type,
                                                row,
                                            )}
                                        </th>
                                    ) : (
                                        <td
                                            key={column.key}
                                            className={cn(
                                                'px-3 py-2 text-ink tabular-nums',
                                                numeric.includes(column.type) &&
                                                    'text-right',
                                            )}
                                        >
                                            {formatCell(
                                                row[column.key],
                                                column.type,
                                                row,
                                            )}
                                        </td>
                                    ),
                                )}
                            </tr>
                        ))}
                    </tbody>
                    {table.totals && (
                        <tfoot>
                            <tr className="border-t-2 border-line-strong font-semibold">
                                {table.columns.map((column, index) => {
                                    const Cell = index === 0 ? 'th' : 'td';

                                    return (
                                        <Cell
                                            key={column.key}
                                            scope={
                                                index === 0 ? 'row' : undefined
                                            }
                                            className={cn(
                                                'px-3 py-2 tabular-nums',
                                                index === 0
                                                    ? 'sticky left-0 bg-surface text-left'
                                                    : numeric.includes(
                                                          column.type,
                                                      ) && 'text-right',
                                            )}
                                        >
                                            {formatCell(
                                                table.totals?.[column.key],
                                                column.type,
                                                table.totals ?? {},
                                            )}
                                        </Cell>
                                    );
                                })}
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
            {table.note && (
                <p className="text-sm text-ink-muted">{table.note}</p>
            )}
        </div>
    );
}

/** A table folded away under its chart; the summary is a real, focusable control. */
export function TableDisclosure({
    table,
    children,
}: {
    table: ReportTableData;
    children?: ReactNode;
}) {
    const t = useT();

    return (
        <details className="group rounded-md">
            <summary className="flex min-h-11 w-fit cursor-pointer items-center text-sm font-semibold text-ember underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember focus-visible:outline-solid">
                {t('staff_reports.table.show')}: {table.title}
            </summary>
            <div className="mt-2">
                <ReportTable table={table} />
                {children}
            </div>
        </details>
    );
}

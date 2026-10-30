import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { HistoryRow } from './types';

export function PriceHistory({ rows }: { rows: HistoryRow[] }) {
    const t = useT();

    if (rows.length === 0) {
        return (
            <p className="text-sm text-ink-muted">
                {t('staff_menu.history.empty')}
            </p>
        );
    }

    return (
        <ol className="divide-y divide-line text-sm">
            {rows.map((row) => (
                <li key={row.id} className="py-2.5">
                    <p className="font-semibold tabular-nums">
                        {t('staff_menu.history.row', {
                            old: formatMoney(row.old),
                            new: formatMoney(row.new),
                        })}
                    </p>
                    <p className="text-ink-muted">
                        {row.variant ?? t('staff_menu.history.base')}
                        {' · '}
                        {row.at}
                        {row.by &&
                            ` · ${t('staff_menu.history.by', { name: row.by })}`}
                    </p>
                </li>
            ))}
        </ol>
    );
}

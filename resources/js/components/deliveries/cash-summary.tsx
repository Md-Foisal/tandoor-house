import { Banknote } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { CashSummary } from './types';

export function CashSummaryPanel({
    cash,
    manages,
}: {
    cash: CashSummary;
    manages: boolean;
}) {
    const t = useT();

    return (
        <section
            aria-labelledby="cash-title"
            className="grid gap-2 rounded-lg border-2 border-ink/80 bg-surface p-4"
        >
            <h2
                id="cash-title"
                className="flex items-center gap-2 text-label text-ink-muted uppercase"
            >
                <Banknote className="size-4" aria-hidden="true" />
                {t('staff_deliveries.cash.title')}
            </h2>
            <p className="font-display text-display-lg tabular-nums">
                {formatMoney(cash.total)}
            </p>
            <p className="text-sm text-ink-muted">
                {cash.total === 0
                    ? t('staff_deliveries.cash.none')
                    : manages
                      ? t('staff_deliveries.cash.body_manager')
                      : t('staff_deliveries.cash.body')}
            </p>
            {manages && cash.by_rider.length > 0 && (
                <ul className="divide-y divide-line text-sm">
                    {cash.by_rider.map((row) => (
                        <li
                            key={row.name}
                            className="flex justify-between py-2"
                        >
                            <span>
                                {row.name ||
                                    t('staff_deliveries.card.no_rider')}
                            </span>
                            <span className="font-semibold tabular-nums">
                                {formatMoney(row.total)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

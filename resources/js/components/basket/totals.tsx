import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { BasketSummary } from './types';

function Row({
    label,
    note,
    value,
    strong = false,
}: {
    label: string;
    note?: string;
    value?: string;
    strong?: boolean;
}) {
    return (
        <div
            className={
                strong
                    ? 'flex items-baseline justify-between gap-4 border-t border-line pt-3 text-title font-semibold text-ink'
                    : 'flex items-baseline justify-between gap-4 text-ink'
            }
        >
            <dt className={strong ? undefined : 'text-ink-muted'}>
                {label}
                {note && (
                    <span className="block text-sm font-normal text-ink-muted">
                        {note}
                    </span>
                )}
            </dt>
            {value && <dd className="tabular-nums">{value}</dd>}
        </div>
    );
}

export function Totals({
    basket,
    serviceRate,
}: {
    basket: BasketSummary;
    serviceRate: number;
}) {
    const t = useT();

    return (
        <section
            aria-labelledby="basket-totals"
            className="rounded-lg border border-line bg-surface p-4"
        >
            <h2 id="basket-totals" className="sr-only">
                {t('basket.totals.heading')}
            </h2>
            <dl
                className="flex flex-col gap-2"
                aria-live="polite"
                aria-atomic="true"
            >
                <Row
                    label={t('basket.totals.subtotal')}
                    value={formatMoney(basket.subtotal)}
                />
                {basket.discount > 0 && (
                    <Row
                        label={`${t('basket.totals.discount')}${basket.promo_code ? ` (${basket.promo_code})` : ''}`}
                        value={`−${formatMoney(basket.discount)}`}
                    />
                )}
                {basket.channel === 'delivery' && (
                    <Row
                        label={t('basket.totals.delivery')}
                        note={t('basket.totals.delivery_note')}
                    />
                )}
                {basket.channel === 'dine_in' && (
                    <Row
                        label={t('basket.totals.service')}
                        note={t('basket.totals.service_note', {
                            rate: serviceRate / 100,
                        })}
                    />
                )}
                <Row
                    label={t('basket.totals.vat')}
                    value={formatMoney(basket.vat_total)}
                />
                <Row
                    strong
                    label={t('basket.totals.total')}
                    value={formatMoney(basket.total)}
                />
            </dl>
        </section>
    );
}

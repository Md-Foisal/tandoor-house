import { Phone } from 'lucide-react';
import { Price } from '@/components/brand/price';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { usePage } from '@inertiajs/react';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { TrackedChannel, TrackedDetails } from './types';

function Row({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
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
            <dt className={strong ? undefined : 'text-ink-muted'}>{label}</dt>
            <dd className="tabular-nums">{value}</dd>
        </div>
    );
}

export function OrderDetailsSkeleton() {
    return (
        <div
            className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-4"
            role="status"
            aria-busy="true"
        >
            <Skeleton className="h-6 w-1/3" />
            {[0, 1, 2].map((row) => (
                <div key={row} className="flex justify-between gap-4">
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-4 w-3/4" />
                        <Skeleton className="h-3 w-1/2" />
                    </div>
                    <Skeleton className="h-4 w-12" />
                </div>
            ))}
            <Skeleton className="mt-2 h-24 w-full" />
        </div>
    );
}

export function OrderDetails({
    details,
    channel,
}: {
    details: TrackedDetails;
    channel: TrackedChannel;
}) {
    const t = useT();
    const { restaurant } = usePage().props;
    const { totals } = details;
    const addressLine = details.address
        ? [details.address.line1, details.address.line2, details.address.city]
              .filter(Boolean)
              .join(', ')
        : null;

    return (
        <div className="flex flex-col gap-6">
            <section
                aria-labelledby="order-items"
                className="rounded-lg border border-line bg-surface p-4"
            >
                <h2
                    id="order-items"
                    className="font-display text-display-md text-ink"
                >
                    {t('tracking.details.items')}
                </h2>
                <ul className="mt-3 divide-y divide-line">
                    {details.lines.map((line) => (
                        <li
                            key={line.id}
                            className="flex items-start justify-between gap-4 py-3"
                        >
                            <div className="min-w-0">
                                <p className="font-semibold text-ink">
                                    <span className="tabular-nums">
                                        {line.quantity} ×
                                    </span>{' '}
                                    {line.name}
                                    {line.variant_name && (
                                        <span className="font-normal text-ink-muted">
                                            {' '}
                                            · {line.variant_name}
                                        </span>
                                    )}
                                </p>
                                {line.modifiers.length > 0 && (
                                    <p className="text-sm text-ink-muted">
                                        {t('tracking.details.with', {
                                            modifiers: line.modifiers
                                                .map((m) => m.name)
                                                .join(', '),
                                        })}
                                    </p>
                                )}
                                {line.notes && (
                                    <p className="text-sm text-ink-muted">
                                        {t('tracking.details.note', {
                                            notes: line.notes,
                                        })}
                                    </p>
                                )}
                            </div>
                            <Price pence={line.line_total} compact={false} />
                        </li>
                    ))}
                </ul>
                <dl className="mt-3 flex flex-col gap-2 border-t border-line pt-3">
                    <Row
                        label={t('tracking.details.subtotal')}
                        value={formatMoney(totals.subtotal)}
                    />
                    {totals.discount > 0 && (
                        <Row
                            label={t('tracking.details.discount')}
                            value={`−${formatMoney(totals.discount)}`}
                        />
                    )}
                    {totals.delivery_fee > 0 && (
                        <Row
                            label={t('tracking.details.delivery_fee')}
                            value={formatMoney(totals.delivery_fee)}
                        />
                    )}
                    {totals.service_charge > 0 && (
                        <Row
                            label={t('tracking.details.service_charge')}
                            value={formatMoney(totals.service_charge)}
                        />
                    )}
                    {totals.tip > 0 && (
                        <Row
                            label={t('tracking.details.tip')}
                            value={formatMoney(totals.tip)}
                        />
                    )}
                    <Row
                        label={t('tracking.details.total')}
                        value={formatMoney(totals.total)}
                        strong
                    />
                    <p className="text-sm text-ink-muted">
                        {t('tracking.details.vat')}:{' '}
                        {formatMoney(totals.vat_total)}
                    </p>
                </dl>
            </section>

            <section className="rounded-lg border border-line bg-surface p-4">
                <dl className="flex flex-col gap-3">
                    <div>
                        <dt className="text-sm text-ink-muted">
                            {t('tracking.details.payment')}
                        </dt>
                        <dd className="font-semibold text-ink">
                            {t(
                                `tracking.payment.method.${details.payment.method}`,
                            )}
                            {' · '}
                            {t(
                                `tracking.payment.status.${details.payment.status}`,
                            )}
                        </dd>
                    </div>
                    {channel === 'delivery' && addressLine && (
                        <div>
                            <dt className="text-sm text-ink-muted">
                                {t('tracking.details.delivery_to')}
                            </dt>
                            <dd className="font-semibold text-ink">
                                {addressLine}
                                {details.postcode && `, ${details.postcode}`}
                            </dd>
                        </div>
                    )}
                    {channel === 'pickup' && details.pickup_label && (
                        <div>
                            <dt className="text-sm text-ink-muted">
                                {t('tracking.details.collect_at')}
                            </dt>
                            <dd className="font-semibold text-ink tabular-nums">
                                {details.pickup_label}
                            </dd>
                        </div>
                    )}
                    {channel === 'dine_in' && details.table && (
                        <div>
                            <dd className="font-semibold text-ink">
                                {t('tracking.details.table', {
                                    label: details.table,
                                })}
                            </dd>
                        </div>
                    )}
                    {details.notes && (
                        <div>
                            <dt className="text-sm text-ink-muted">
                                {t('tracking.details.order_notes')}
                            </dt>
                            <dd className="text-ink">{details.notes}</dd>
                        </div>
                    )}
                </dl>
            </section>

            {restaurant?.phone && (
                <section className="flex flex-col items-start gap-2 rounded-lg border border-line bg-clay p-4">
                    <h2 className="font-semibold text-ink">
                        {t('tracking.details.contact_title')}
                    </h2>
                    <p className="text-ink-muted">
                        {t('tracking.details.contact_body')}
                    </p>
                    <Button asChild variant="outline" className="min-h-11">
                        <a
                            href={`tel:${restaurant.phone.replace(/\s+/g, '')}`}
                            aria-label={t('tracking.details.call_label', {
                                name: restaurant.name,
                                phone: restaurant.phone,
                            })}
                        >
                            <Phone className="size-4" aria-hidden="true" />
                            {t('tracking.details.call')} · {restaurant.phone}
                        </a>
                    </Button>
                </section>
            )}
        </div>
    );
}

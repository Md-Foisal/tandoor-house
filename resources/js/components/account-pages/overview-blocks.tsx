import { Link } from '@inertiajs/react';
import { Check, Copy, MapPin } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Price } from '@/components/brand/price';
import { EmptyState } from '@/components/brand/states';
import { Course, Plate } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { formatDate, formatDateTime, summarise } from './format';
import { ReorderButton } from './reorder-button';
import { StatusBadge } from './status-badge';
import type {
    ActiveOrder,
    DefaultAddress,
    Promo,
    RecentOrder,
    UpcomingReservation,
} from './types';

function Block({
    id,
    title,
    action,
    children,
}: {
    id: string;
    title: string;
    action?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <section aria-labelledby={id} className="flex flex-col gap-3">
            <div className="flex items-baseline justify-between gap-3">
                <h2 id={id} className="font-display text-display-md">
                    {title}
                </h2>
                {action}
            </div>
            {children}
        </section>
    );
}

export function ActiveOrders({ orders }: { orders: ActiveOrder[] }) {
    const t = useT();

    return (
        <Block id="active-title" title={t('account.active.title')}>
            {orders.length === 0 ? (
                <EmptyState
                    className="rounded-lg border border-dashed border-line py-8"
                    title={t('account.active.empty_title')}
                    description={t('account.active.empty_body')}
                    action={
                        <Button asChild variant="outline">
                            <Link href="/menu" prefetch>
                                {t('account.active.empty_action')}
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <ul className="flex flex-col gap-3">
                    {orders.map((order) => (
                        <li key={order.number}>
                            <Link
                                href={`/orders/${order.number}`}
                                aria-label={t('account.active.track', {
                                    number: order.number,
                                })}
                                className="flex min-h-11 flex-wrap items-center justify-between gap-3 rounded-lg border border-turmeric/50 bg-turmeric-soft p-4 outline-none hover:border-turmeric focus-visible:outline-2 focus-visible:outline-ember"
                            >
                                <span className="flex flex-col">
                                    <span className="font-semibold">
                                        {order.number}
                                    </span>
                                    <span className="text-sm text-ink-muted">
                                        {t(`account.channel.${order.channel}`)}
                                        {' · '}
                                        {t('account.active.placed', {
                                            date: formatDateTime(
                                                order.placed_at,
                                            ),
                                        })}
                                    </span>
                                </span>
                                <span className="flex items-center gap-3">
                                    <StatusBadge status={order.status} />
                                    <Price
                                        pence={order.total}
                                        compact={false}
                                    />
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </Block>
    );
}

export function RecentOrders({ orders }: { orders: RecentOrder[] }) {
    const t = useT();

    return (
        <Block
            id="recent-title"
            title={t('account.recent.title')}
            action={
                <Button asChild variant="link">
                    <Link href="/account/orders" prefetch>
                        {t('account.recent.all')}
                    </Link>
                </Button>
            }
        >
            {orders.length === 0 ? (
                <EmptyState
                    className="rounded-lg border border-dashed border-line py-8"
                    title={t('account.recent.empty_title')}
                    description={t('account.recent.empty_body')}
                    action={
                        <Button asChild variant="outline">
                            <Link href="/menu" prefetch>
                                {t('account.recent.empty_action')}
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <Course role="list" className="flex flex-col gap-3">
                    {orders.map((order) => {
                        const summary = summarise(order.lines);

                        return (
                            <Plate role="listitem" key={order.number}>
                                <article className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-line bg-surface p-4">
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={`/orders/${order.number}`}
                                            className="font-semibold outline-none hover:text-ember focus-visible:outline-2 focus-visible:outline-ember"
                                        >
                                            {order.number}
                                        </Link>
                                        <p className="text-sm text-ink-muted">
                                            {formatDateTime(order.placed_at)}
                                            {' · '}
                                            <Price
                                                pence={order.total}
                                                compact={false}
                                                className="font-normal"
                                            />
                                        </p>
                                        <p className="mt-1 truncate">
                                            {summary.text}
                                            {summary.extra > 0 &&
                                                ` ${t('account.orders.more', { count: summary.extra })}`}
                                        </p>
                                    </div>
                                    <ReorderButton number={order.number} />
                                </article>
                            </Plate>
                        );
                    })}
                </Course>
            )}
        </Block>
    );
}

function PromoRow({ promo }: { promo: Promo }) {
    const t = useT();
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(promo.code);
            setCopied(true);
            toast.success(
                t('account.promos.copied_toast', { code: promo.code }),
            );
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error(t('account.promos.copy_failed'));
        }
    };

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-dashed border-line-strong bg-surface p-4">
            <div className="min-w-0 flex-1">
                <p className="font-semibold tracking-wide tabular-nums">
                    {promo.code}
                </p>
                <p className="text-ink">{promo.description}</p>
                <p className="text-sm text-ink-muted">
                    {promo.minimum_order > 0
                        ? t('account.promos.minimum', {
                              amount: formatMoney(promo.minimum_order, {
                                  compact: true,
                              }),
                          })
                        : t('account.promos.no_minimum')}
                    {promo.ends_at &&
                        ` · ${t('account.promos.until', { date: formatDate(promo.ends_at) })}`}
                </p>
            </div>
            <Button
                variant="outline"
                onClick={copy}
                aria-label={t('account.promos.copy_label', {
                    code: promo.code,
                })}
            >
                {copied ? (
                    <Check aria-hidden="true" />
                ) : (
                    <Copy aria-hidden="true" />
                )}
                <span aria-live="polite">
                    {copied
                        ? t('account.promos.copied')
                        : t('account.promos.copy')}
                </span>
            </Button>
        </li>
    );
}

export function PromoWallet({ promos }: { promos: Promo[] }) {
    const t = useT();

    return (
        <Block id="promos-title" title={t('account.promos.title')}>
            {promos.length === 0 ? (
                <EmptyState
                    className="rounded-lg border border-dashed border-line py-8"
                    title={t('account.promos.empty_title')}
                    description={t('account.promos.empty_body')}
                />
            ) : (
                <ul className="flex flex-col gap-3">
                    {promos.map((promo) => (
                        <PromoRow key={promo.code} promo={promo} />
                    ))}
                </ul>
            )}
        </Block>
    );
}

export function Reservations({
    reservations,
}: {
    reservations: UpcomingReservation[];
}) {
    const t = useT();

    return (
        <Block id="bookings-title" title={t('account.bookings.title')}>
            {reservations.length === 0 ? (
                <EmptyState
                    className="rounded-lg border border-dashed border-line py-8"
                    title={t('account.bookings.empty_title')}
                    description={t('account.bookings.empty_body')}
                    action={
                        <Button asChild variant="outline">
                            <Link href="/book" prefetch>
                                {t('account.bookings.empty_action')}
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <ul className="flex flex-col gap-3">
                    {reservations.map((reservation) => (
                        <li key={reservation.reference}>
                            <Link
                                href={`/book/${reservation.reference}`}
                                aria-label={t('account.bookings.view', {
                                    reference: reservation.reference,
                                })}
                                className="flex min-h-11 flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-surface p-4 outline-none hover:border-ember focus-visible:outline-2 focus-visible:outline-ember"
                            >
                                <span className="flex flex-col">
                                    <span className="font-semibold">
                                        {formatDateTime(reservation.starts_at)}
                                    </span>
                                    <span className="text-sm text-ink-muted">
                                        {t('account.bookings.party', {
                                            count: reservation.party_size,
                                        })}
                                    </span>
                                </span>
                                <span
                                    className={
                                        reservation.status === 'pending_deposit'
                                            ? 'text-sm font-semibold text-turmeric'
                                            : 'text-sm font-semibold text-leaf'
                                    }
                                >
                                    {t(
                                        `account.bookings.${reservation.status}`,
                                    )}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </Block>
    );
}

export function AddressSummary({
    address,
}: {
    address: DefaultAddress | null;
}) {
    const t = useT();

    return (
        <Block id="address-title" title={t('account.address.title')}>
            {address === null ? (
                <EmptyState
                    className="rounded-lg border border-dashed border-line py-8"
                    title={t('account.address.empty_title')}
                    description={t('account.address.empty_body')}
                    action={
                        <Button asChild variant="outline">
                            <Link href="/account/addresses" prefetch>
                                {t('account.address.empty_action')}
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <div className="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-line bg-surface p-4">
                    <div className="flex gap-3">
                        <MapPin
                            className="mt-1 size-5 shrink-0 text-ember"
                            aria-hidden="true"
                        />
                        <address className="not-italic">
                            <p className="font-semibold">{address.label}</p>
                            <p>{address.line1}</p>
                            {address.line2 && <p>{address.line2}</p>}
                            <p>
                                {address.city}, {address.postcode}
                            </p>
                        </address>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/account/addresses" prefetch>
                            {t('account.address.manage')}
                        </Link>
                    </Button>
                </div>
            )}
        </Block>
    );
}

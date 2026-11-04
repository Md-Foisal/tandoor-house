import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { StatTile } from '@/components/staff/stat-tile';
import { Elapsed } from '@/components/staff/elapsed';
import { Skeleton } from '@/components/ui/skeleton';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { BlockSkeleton, RowLink } from './block';
import { Sparkline } from './sparkline';
import { clockTime, type ManagementBlocks } from './types';

export function ManagementSkeleton() {
    return (
        <div className="space-y-4" aria-busy="true">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {Array.from({ length: 7 }, (_, index) => (
                    <Skeleton key={index} className="h-28" />
                ))}
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                <Skeleton className="h-52" />
                <BlockSkeleton rows={4} />
            </div>
        </div>
    );
}

export function Management({
    blocks,
    weekday,
}: {
    blocks: ManagementBlocks;
    weekday: string;
}) {
    const t = useT();
    const { tiles, attention } = blocks;
    const channels = tiles.orders_by_channel;
    const orderTotal = channels.delivery + channels.pickup + channels.dine_in;

    const reviewCount = attention.unpublished_reviews;
    const needs =
        attention.slow_orders.length +
        attention.table_requests.length +
        attention.unpaid_deposits.length +
        attention.low_stock.length +
        reviewCount;

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile
                    label={t('staff_today.tiles.sales')}
                    value={formatMoney(tiles.sales)}
                    hint={t('staff_today.tiles.sales_hint')}
                />
                <StatTile
                    label={t('staff_today.tiles.orders')}
                    value={orderTotal}
                    hint={t('staff_today.tiles.orders_hint', {
                        delivery: channels.delivery,
                        pickup: channels.pickup,
                        dine_in: channels.dine_in,
                    })}
                />
                <StatTile
                    label={t('staff_today.tiles.covers')}
                    value={tiles.covers_tonight}
                    hint={t('staff_today.tiles.covers_hint')}
                />
                <StatTile
                    label={t('staff_today.tiles.prep')}
                    value={
                        tiles.average_prep_minutes === null
                            ? '–'
                            : t('staff_today.tiles.prep_value', {
                                  minutes: tiles.average_prep_minutes,
                              })
                    }
                    hint={
                        tiles.average_prep_minutes === null
                            ? t('staff_today.tiles.prep_none')
                            : t('staff_today.tiles.prep_hint')
                    }
                />
                <StatTile
                    label={t('staff_today.tiles.late')}
                    value={tiles.late_tickets}
                    tone={tiles.late_tickets > 0 ? 'bad' : 'good'}
                    hint={
                        tiles.late_tickets > 0
                            ? t('staff_today.tiles.late_hint')
                            : t('staff_today.tiles.late_none')
                    }
                />
                <StatTile
                    label={t('staff_today.tiles.low_stock')}
                    value={tiles.low_stock}
                    tone={tiles.low_stock > 0 ? 'warn' : 'good'}
                    hint={
                        tiles.low_stock > 0
                            ? t('staff_today.tiles.low_stock_hint')
                            : t('staff_today.tiles.low_stock_none')
                    }
                />
                <StatTile
                    label={t('staff_today.tiles.staff')}
                    value={tiles.staff_on_shift}
                    hint={t('staff_today.tiles.staff_hint')}
                />
            </div>

            <div className="grid items-start gap-4 lg:grid-cols-2">
                <Sparkline points={blocks.hourly} weekday={weekday} />

                <section className="rounded-lg border border-line bg-surface">
                    <header className="border-b border-line px-4 py-3">
                        <h2 className="text-title font-semibold">
                            {t('staff_today.attention.title')}
                        </h2>
                    </header>
                    {needs === 0 ? (
                        <div className="flex items-center gap-3 px-4 py-5">
                            <Check className="size-5 text-leaf" aria-hidden />
                            <div>
                                <p className="font-semibold">
                                    {t('staff_today.attention.clear')}
                                </p>
                                <p className="text-sm text-ink-muted">
                                    {t('staff_today.attention.clear_hint')}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <ul className="divide-y divide-line">
                            {attention.slow_orders.map((order) => (
                                <RowLink
                                    key={`o${order.id}`}
                                    href={`/staff/orders/${order.number}`}
                                >
                                    <span>
                                        <span className="block text-label text-ink-muted">
                                            {t(
                                                'staff_today.attention.slow_label',
                                            )}
                                        </span>
                                        <span className="font-semibold tabular-nums">
                                            {order.number}
                                        </span>
                                    </span>
                                    <Elapsed
                                        since={order.since}
                                        warnAfter={3}
                                        lateAfter={5}
                                        className="text-lg"
                                    />
                                </RowLink>
                            ))}
                            {attention.table_requests.map((request) => (
                                <RowLink
                                    key={`r${request.id}`}
                                    href="/staff/floor"
                                >
                                    <span>
                                        <span className="block text-label text-ink-muted">
                                            {t(
                                                'staff_today.attention.requests',
                                            )}
                                        </span>
                                        {t('staff_today.request.line', {
                                            type: t(
                                                `staff_today.request.${request.type}`,
                                            ),
                                            table: request.table,
                                        })}
                                    </span>
                                    <Elapsed
                                        since={request.since}
                                        warnAfter={2}
                                        lateAfter={5}
                                        className="text-lg"
                                    />
                                </RowLink>
                            ))}
                            {attention.unpaid_deposits.map((deposit) => (
                                <RowLink
                                    key={`d${deposit.id}`}
                                    href="/staff/reservations"
                                >
                                    <span>
                                        <span className="block text-label text-ink-muted">
                                            {t(
                                                'staff_today.attention.deposits',
                                            )}
                                        </span>
                                        {t(
                                            'staff_today.attention.deposit_line',
                                            {
                                                name: deposit.name,
                                                size: deposit.party_size,
                                                time: clockTime(
                                                    deposit.starts_at,
                                                ),
                                            },
                                        )}
                                    </span>
                                    <span className="font-semibold tabular-nums">
                                        {formatMoney(deposit.deposit)}
                                    </span>
                                </RowLink>
                            ))}
                            {attention.low_stock.map((item) => (
                                <RowLink
                                    key={`s${item.id}`}
                                    href="/staff/inventory"
                                >
                                    <span>
                                        <span className="block text-label text-ink-muted">
                                            {t('staff_today.attention.stock')}
                                        </span>
                                        {t('staff_today.attention.stock_line', {
                                            name: item.name,
                                            stock: item.stock,
                                            unit: item.unit,
                                        })}
                                    </span>
                                </RowLink>
                            ))}
                            {reviewCount > 0 && (
                                <RowLink href="/staff/reviews">
                                    <span>
                                        <span className="block text-label text-ink-muted">
                                            {t('staff_today.attention.reviews')}
                                        </span>
                                        {choice(
                                            t(
                                                'staff_today.attention.reviews_count',
                                                { count: reviewCount },
                                            ),
                                            reviewCount,
                                        )}
                                    </span>
                                </RowLink>
                            )}
                        </ul>
                    )}
                </section>
            </div>

            <p className="text-sm text-ink-muted">
                <Link
                    href="/staff/orders"
                    className="inline-flex min-h-11 items-center underline"
                >
                    {t('staff_today.front.open_orders')}
                </Link>
            </p>
        </div>
    );
}

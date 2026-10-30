import { Link } from '@inertiajs/react';
import { AlertTriangle, IdCard, StickyNote } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Elapsed } from '@/components/staff/elapsed';
import { ChannelBadge } from '@/components/staff/status-badge';
import { OrderStatusBadge } from '@/components/staff/status-badge';
import { formatMoney } from '@/lib/money';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { formatTime } from './format';
import { OrderActions } from './order-actions';
import { isLive, type OrderCardData } from './types';

export function PaymentLine({
    method,
    status,
}: {
    method: OrderCardData['payment_method'];
    status: OrderCardData['payment_status'];
}) {
    const t = useT();
    const settled = status === 'paid';

    return (
        <span className="inline-flex flex-wrap items-center gap-1.5 text-sm">
            <span className="text-ink-muted">
                {t(`staff_orders.payment.method.${method}`)}
            </span>
            <Badge
                variant={
                    settled
                        ? 'leaf'
                        : status === 'pending'
                          ? 'turmeric'
                          : 'secondary'
                }
            >
                {t(`staff_orders.payment.status.${status}`)}
            </Badge>
        </span>
    );
}

export function OrderCard({
    order,
    highlight,
}: {
    order: OrderCardData;
    highlight?: boolean;
}) {
    const t = useT();
    const live = isLive(order.status);
    const shown = order.items.slice(0, 3);
    const hidden = order.items.length - shown.length;
    const where =
        order.channel === 'delivery'
            ? order.postcode
            : order.channel === 'pickup' && order.pickup_at
              ? t('staff_orders.card.pickup_at', {
                    time: formatTime(order.pickup_at),
                })
              : null;

    return (
        <article
            data-order={order.number}
            aria-label={t('staff_orders.card.open', { number: order.number })}
            className={cn(
                'flex flex-col gap-3 rounded-lg border bg-surface p-4 transition-shadow duration-700',
                highlight
                    ? 'border-ember shadow-[0_0_0_3px_var(--ember-soft)]'
                    : 'border-line',
            )}
        >
            <header className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <Link
                        href={`/staff/orders/${order.number}`}
                        className="inline-flex min-h-11 items-center font-display text-title underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ember"
                        aria-label={t('staff_orders.card.open', {
                            number: order.number,
                        })}
                    >
                        {order.number}
                    </Link>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <ChannelBadge
                            channel={order.channel}
                            table={order.table}
                        />
                        {where && (
                            <span className="text-sm text-ink-muted">
                                {where}
                            </span>
                        )}
                        <span className="text-sm font-semibold">
                            {order.first_name}
                        </span>
                    </div>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                    {live ? (
                        <Elapsed
                            since={order.placed_at}
                            className="text-title"
                            warnAfter={order.status === 'placed' ? 3 : 15}
                            lateAfter={order.status === 'placed' ? 6 : 30}
                        />
                    ) : (
                        <OrderStatusBadge status={order.status} />
                    )}
                    <span className="font-semibold tabular-nums">
                        {formatMoney(order.total)}
                    </span>
                </div>
            </header>

            <ul className="grid gap-0.5 text-sm">
                {shown.map((item, index) => (
                    <li key={index} className="flex gap-2">
                        <span className="w-6 shrink-0 font-semibold tabular-nums">
                            {item.quantity}×
                        </span>
                        <span className="min-w-0">
                            {item.name}
                            {item.variant && (
                                <span className="text-ink-muted">
                                    {' '}
                                    ({item.variant})
                                </span>
                            )}
                            {item.modifiers.length > 0 && (
                                <span className="text-ink-muted">
                                    {' '}
                                    + {item.modifiers.join(', ')}
                                </span>
                            )}
                        </span>
                    </li>
                ))}
                {hidden > 0 && (
                    <li className="pl-8 text-ink-muted">
                        {t('staff_orders.card.more_items', { count: hidden })}
                    </li>
                )}
            </ul>

            {(order.notes || order.has_allergy || order.age_check) && (
                <div className="grid gap-1.5">
                    {order.has_allergy && (
                        <p className="flex items-start gap-2 rounded-md bg-chilli-soft px-2.5 py-1.5 text-sm font-semibold text-chilli">
                            <AlertTriangle
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {t('staff_orders.card.allergy')}
                            {order.notes ? `: ${order.notes}` : ''}
                        </p>
                    )}
                    {order.notes && !order.has_allergy && (
                        <p className="flex items-start gap-2 rounded-md bg-turmeric-soft px-2.5 py-1.5 text-sm text-turmeric">
                            <StickyNote
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {order.notes}
                        </p>
                    )}
                    {order.age_check && (
                        <p className="flex items-center gap-2 rounded-md bg-turmeric-soft px-2.5 py-1.5 text-sm font-semibold text-turmeric">
                            <IdCard
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {t('staff_orders.card.age_check')}
                        </p>
                    )}
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-2">
                <PaymentLine
                    method={order.payment_method}
                    status={order.payment_status}
                />
                {order.channel === 'delivery' && live && (
                    <span className="text-sm text-ink-muted">
                        {order.rider
                            ? t('staff_orders.card.rider', {
                                  name: order.rider,
                              })
                            : t('staff_orders.card.no_rider')}
                    </span>
                )}
            </div>

            <OrderActions order={order} only={['orders']} />
        </article>
    );
}

export function OrderCardSkeleton() {
    return (
        <div className="grid gap-3 rounded-lg border border-line bg-surface p-4">
            <div className="flex justify-between gap-3">
                <div className="grid gap-2">
                    <Skeleton className="h-6 w-28" />
                    <Skeleton className="h-4 w-36" />
                </div>
                <Skeleton className="h-10 w-14" />
            </div>
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-2/3" />
            <Skeleton className="h-12 w-full" />
        </div>
    );
}

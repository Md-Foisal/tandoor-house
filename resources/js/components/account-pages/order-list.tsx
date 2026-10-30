import { Link } from '@inertiajs/react';
import { Price } from '@/components/brand/price';
import { Course, Plate } from '@/components/motion/served';
import { useT } from '@/lib/i18n';
import { formatDateTime, summarise } from './format';
import { ReorderButton } from './reorder-button';
import { StatusBadge } from './status-badge';
import type { HistoryOrder } from './types';

export function OrderList({ orders }: { orders: HistoryOrder[] }) {
    const t = useT();

    return (
        <Course
            role="list"
            aria-label={t('account.orders.list_label')}
            className="flex flex-col gap-3"
        >
            {orders.map((order) => {
                const summary = summarise(order.lines);

                return (
                    <Plate role="listitem" key={order.number}>
                        <article className="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4 md:flex-row md:items-center md:justify-between md:gap-6">
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <Link
                                        href={`/orders/${order.number}`}
                                        aria-label={t('account.orders.view', {
                                            number: order.number,
                                        })}
                                        className="font-semibold outline-hidden hover:text-ember focus-visible:outline-2 focus-visible:outline-ember"
                                    >
                                        {order.number}
                                    </Link>
                                    <StatusBadge status={order.status} />
                                </div>
                                <p className="text-sm text-ink-muted">
                                    {formatDateTime(order.placed_at)}
                                    {' · '}
                                    {t(`account.channel.${order.channel}`)}
                                </p>
                                <p className="mt-1">
                                    {summary.text}
                                    {summary.extra > 0 &&
                                        ` ${t('account.orders.more', { count: summary.extra })}`}
                                </p>
                            </div>
                            <div className="flex items-center justify-between gap-4 md:justify-end">
                                <Price pence={order.total} compact={false} />
                                {order.status === 'completed' && (
                                    <ReorderButton number={order.number} />
                                )}
                            </div>
                        </article>
                    </Plate>
                );
            })}
        </Course>
    );
}

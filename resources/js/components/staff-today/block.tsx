import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { Elapsed } from '@/components/staff/elapsed';
import { ChannelBadge } from '@/components/staff/status-badge';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { OrderRow } from './types';

export function Block({
    title,
    count,
    empty,
    children,
    className,
}: {
    title: string;
    count?: number;
    empty: string;
    children: ReactNode[] | ReactNode;
    className?: string;
}) {
    const items = Array.isArray(children) ? children : [children];
    const none = count === 0 || (count === undefined && items.length === 0);

    return (
        <section
            className={cn(
                'rounded-lg border border-line bg-surface',
                className,
            )}
        >
            <header className="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                <h2 className="text-title font-semibold">{title}</h2>
                {count !== undefined && count > 0 && (
                    <span className="min-w-6 rounded-full bg-clay px-2 py-0.5 text-center text-sm font-semibold tabular-nums">
                        {count}
                    </span>
                )}
            </header>
            {none ? (
                <p className="px-4 py-5 text-sm text-ink-muted">{empty}</p>
            ) : (
                <ul className="divide-y divide-line">{children}</ul>
            )}
        </section>
    );
}

export function RowLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    return (
        <li>
            <Link
                href={href}
                className="flex min-h-14 items-center justify-between gap-3 px-4 py-2.5 hover:bg-clay focus-visible:bg-clay"
            >
                {children}
            </Link>
        </li>
    );
}

export function OrderListRow({
    order,
    warnAfter = 3,
    lateAfter = 5,
}: {
    order: OrderRow;
    warnAfter?: number;
    lateAfter?: number;
}) {
    const t = useT();

    return (
        <RowLink href={`/staff/orders/${order.number}`}>
            <span className="min-w-0">
                <span className="flex flex-wrap items-center gap-2">
                    <span className="font-semibold tabular-nums">
                        {order.number}
                    </span>
                    <ChannelBadge channel={order.channel} />
                    {order.table && (
                        <span className="text-sm text-ink-muted">
                            {t('staff_today.front.table', {
                                table: order.table,
                            })}
                        </span>
                    )}
                </span>
                {order.name && (
                    <span className="block truncate text-sm text-ink-muted">
                        {t('staff_today.order.for', { name: order.name })}
                        {' · '}
                        {formatMoney(order.total)}
                    </span>
                )}
            </span>
            <Elapsed
                since={order.since}
                warnAfter={warnAfter}
                lateAfter={lateAfter}
                className="text-lg"
            />
        </RowLink>
    );
}

export function BlockSkeleton({ rows = 3 }: { rows?: number }) {
    return (
        <div className="rounded-lg border border-line bg-surface">
            <div className="border-b border-line px-4 py-3">
                <Skeleton className="h-6 w-40" />
            </div>
            <div className="space-y-3 p-4">
                {Array.from({ length: rows }, (_, index) => (
                    <Skeleton key={index} className="h-10 w-full" />
                ))}
            </div>
        </div>
    );
}

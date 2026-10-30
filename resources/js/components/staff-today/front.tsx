import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Elapsed } from '@/components/staff/elapsed';
import { useT } from '@/lib/i18n';
import { Block, BlockSkeleton, OrderListRow, RowLink } from './block';
import { clockTime, type FrontBlocks } from './types';

export function FrontSkeleton() {
    return (
        <div className="grid gap-4 lg:grid-cols-2" aria-busy="true">
            <BlockSkeleton />
            <BlockSkeleton />
            <BlockSkeleton />
            <BlockSkeleton />
        </div>
    );
}

export function Front({ blocks }: { blocks: FrontBlocks }) {
    const t = useT();

    return (
        <div className="space-y-4">
            <div className="grid items-start gap-4 lg:grid-cols-2">
                <Block
                    title={t('staff_today.front.waiting')}
                    count={blocks.waiting.length}
                    empty={t('staff_today.front.waiting_empty')}
                >
                    {blocks.waiting.map((order) => (
                        <OrderListRow
                            key={order.id}
                            order={order}
                            warnAfter={3}
                            lateAfter={5}
                        />
                    ))}
                </Block>
                <Block
                    title={t('staff_today.front.ready')}
                    count={blocks.ready.length}
                    empty={t('staff_today.front.ready_empty')}
                >
                    {blocks.ready.map((order) => (
                        <OrderListRow
                            key={order.id}
                            order={order}
                            warnAfter={5}
                            lateAfter={10}
                        />
                    ))}
                </Block>
                <Block
                    title={t('staff_today.front.requests')}
                    count={blocks.table_requests.length}
                    empty={t('staff_today.front.requests_empty')}
                >
                    {blocks.table_requests.map((request) => (
                        <RowLink key={request.id} href="/staff/floor">
                            <span className="font-semibold">
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
                </Block>
                <Block
                    title={t('staff_today.front.bookings')}
                    count={blocks.bookings.length}
                    empty={t('staff_today.front.bookings_empty')}
                >
                    {blocks.bookings.map((booking) => (
                        <RowLink key={booking.id} href="/staff/reservations">
                            <span className="min-w-0">
                                <span className="block truncate font-semibold">
                                    {booking.name}
                                </span>
                                <span className="block text-sm text-ink-muted">
                                    {t('staff_today.front.party', {
                                        size: booking.party_size,
                                    })}
                                    {booking.table &&
                                        ` · ${t('staff_today.front.table', { table: booking.table })}`}
                                    {booking.deposit_pending &&
                                        ` · ${t('staff_today.front.deposit_pending')}`}
                                </span>
                            </span>
                            <span className="text-lg font-semibold tabular-nums">
                                {clockTime(booking.starts_at)}
                            </span>
                        </RowLink>
                    ))}
                </Block>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button asChild size="lg" className="min-h-12">
                    <Link href="/staff/orders">
                        {t('staff_today.front.open_orders')}
                    </Link>
                </Button>
                <Button
                    asChild
                    size="lg"
                    variant="outline"
                    className="min-h-12"
                >
                    <Link href="/staff/floor">
                        {t('staff_today.front.open_floor')}
                    </Link>
                </Button>
                <Button
                    asChild
                    size="lg"
                    variant="outline"
                    className="min-h-12"
                >
                    <Link href="/staff/reservations">
                        {t('staff_today.front.open_bookings')}
                    </Link>
                </Button>
            </div>
        </div>
    );
}

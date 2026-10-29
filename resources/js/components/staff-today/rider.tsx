import { Link } from '@inertiajs/react';
import { Bike } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Elapsed } from '@/components/staff/elapsed';
import { StatTile } from '@/components/staff/stat-tile';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { Block, BlockSkeleton, RowLink } from './block';
import type { DeliveryRow, RiderBlocks } from './types';

export function RiderSkeleton() {
    return (
        <div className="space-y-4" aria-busy="true">
            <BlockSkeleton rows={1} />
            <BlockSkeleton />
            <BlockSkeleton />
        </div>
    );
}

function Row({ delivery }: { delivery: DeliveryRow }) {
    const t = useT();

    return (
        <RowLink href="/staff/deliveries">
            <span className="min-w-0">
                <span className="block font-semibold tabular-nums">
                    {delivery.number}
                    {delivery.postcode && (
                        <span className="ml-2 font-normal text-ink-muted">
                            {delivery.postcode}
                        </span>
                    )}
                </span>
                <span className="block truncate text-sm text-ink-muted">
                    {delivery.name &&
                        t('staff_today.order.for', { name: delivery.name })}
                    {delivery.cash_due > 0 &&
                        ` · ${t('staff_today.rider.cash_due', { amount: formatMoney(delivery.cash_due) })}`}
                </span>
            </span>
            <Elapsed
                since={delivery.since}
                warnAfter={10}
                lateAfter={20}
                className="text-lg"
            />
        </RowLink>
    );
}

export function Rider({ blocks }: { blocks: RiderBlocks }) {
    const t = useT();

    return (
        <div className="space-y-4">
            <Button
                asChild
                size="lg"
                className="min-h-16 w-full text-lg sm:w-auto sm:min-w-80"
            >
                <Link href="/staff/deliveries">
                    <Bike className="size-6" aria-hidden />
                    {t('staff_today.rider.open')}
                </Link>
            </Button>
            <StatTile
                className="sm:max-w-xs"
                label={t('staff_today.rider.cash')}
                value={formatMoney(blocks.cash_collected)}
                hint={t('staff_today.rider.cash_hint', {
                    count: blocks.delivered_today,
                })}
            />
            <div className="grid items-start gap-4 lg:grid-cols-2">
                <Block
                    title={t('staff_today.rider.to_collect')}
                    count={blocks.to_collect.length}
                    empty={t('staff_today.rider.to_collect_empty')}
                >
                    {blocks.to_collect.map((d) => (
                        <Row key={d.id} delivery={d} />
                    ))}
                </Block>
                <Block
                    title={t('staff_today.rider.out_now')}
                    count={blocks.out_now.length}
                    empty={t('staff_today.rider.out_now_empty')}
                >
                    {blocks.out_now.map((d) => (
                        <Row key={d.id} delivery={d} />
                    ))}
                </Block>
            </div>
        </div>
    );
}

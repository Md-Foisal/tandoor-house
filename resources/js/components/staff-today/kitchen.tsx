import { Link } from '@inertiajs/react';
import { ChefHat } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { StatTile } from '@/components/staff/stat-tile';
import { useT } from '@/lib/i18n';
import { Block, BlockSkeleton, OrderListRow, RowLink } from './block';
import type { KitchenBlocks } from './types';

export function KitchenSkeleton() {
    return (
        <div className="space-y-4" aria-busy="true">
            <BlockSkeleton rows={1} />
            <BlockSkeleton />
        </div>
    );
}

export function Kitchen({ blocks }: { blocks: KitchenBlocks }) {
    const t = useT();

    return (
        <div className="space-y-4">
            <Button
                asChild
                size="lg"
                className="min-h-20 w-full text-xl sm:w-auto sm:min-w-80"
            >
                <Link href="/staff/kitchen">
                    <ChefHat className="size-6" aria-hidden />
                    {t('staff_today.kitchen.open_display')}
                </Link>
            </Button>
            <div className="grid grid-cols-2 gap-3 sm:max-w-lg">
                <StatTile
                    label={t('staff_today.kitchen.in_kitchen')}
                    value={blocks.in_kitchen}
                    hint={
                        blocks.in_kitchen === 0
                            ? t('staff_today.kitchen.in_kitchen_empty')
                            : undefined
                    }
                />
                <StatTile
                    label={t('staff_today.kitchen.late')}
                    value={blocks.late}
                    tone={blocks.late > 0 ? 'bad' : 'good'}
                    hint={
                        blocks.late === 0
                            ? t('staff_today.kitchen.late_empty')
                            : undefined
                    }
                />
            </div>
            <div className="grid items-start gap-4 lg:grid-cols-2">
                <Block
                    title={t('staff_today.kitchen.late')}
                    count={blocks.late}
                    empty={t('staff_today.kitchen.late_empty')}
                >
                    {blocks.late_tickets.map((order) => (
                        <OrderListRow
                            key={order.id}
                            order={order}
                            warnAfter={10}
                            lateAfter={15}
                        />
                    ))}
                </Block>
                <Block
                    title={t('staff_today.kitchen.stock')}
                    count={blocks.low_stock_count}
                    empty={t('staff_today.kitchen.stock_empty')}
                >
                    {blocks.low_stock.map((item) => (
                        <RowLink key={item.id} href="/staff/inventory">
                            <span className="font-semibold">{item.name}</span>
                            <span className="text-chilli tabular-nums">
                                {item.stock} {item.unit}
                            </span>
                        </RowLink>
                    ))}
                </Block>
            </div>
        </div>
    );
}

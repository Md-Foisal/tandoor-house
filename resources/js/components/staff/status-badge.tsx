import { Bike, ShoppingBag, UtensilsCrossed } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';
import type { OrderChannel, OrderStatus } from '@/types';

const tone: Record<
    OrderStatus,
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'leaf'
    | 'turmeric'
    | 'solid'
    | 'outline'
> = {
    placed: 'solid',
    accepted: 'turmeric',
    cooking: 'default',
    ready: 'leaf',
    out_for_delivery: 'secondary',
    served: 'secondary',
    collected: 'secondary',
    delivered: 'secondary',
    completed: 'outline',
    rejected: 'destructive',
    cancelled: 'destructive',
};

export function OrderStatusBadge({ status }: { status: OrderStatus }) {
    const t = useT();

    return <Badge variant={tone[status]}>{t(`staff.status.${status}`)}</Badge>;
}

const channelIcon = {
    delivery: Bike,
    pickup: ShoppingBag,
    dine_in: UtensilsCrossed,
} as const;

export function ChannelBadge({
    channel,
    table,
}: {
    channel: OrderChannel;
    table?: string | null;
}) {
    const t = useT();
    const Icon = channelIcon[channel];

    return (
        <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-muted">
            <Icon className="size-4" aria-hidden="true" />
            {channel === 'dine_in' && table
                ? table
                : t(`staff.channel.${channel}`)}
        </span>
    );
}

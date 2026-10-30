import { Link } from '@inertiajs/react';
import { Bike, ShoppingBag, UtensilsCrossed } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { BasketChannel, BasketPageProps } from './types';

const icons = {
    delivery: Bike,
    pickup: ShoppingBag,
    dine_in: UtensilsCrossed,
} as const;

/**
 * Shows the chosen way of getting the order. Changing it happens on the
 * checkout page, where the postcode and pickup time are asked for anyway.
 */
export function ChannelSummary({
    channel,
    table,
    delivery,
}: {
    channel: BasketChannel;
    table: string | null;
    delivery: BasketPageProps['delivery'];
}) {
    const t = useT();
    const Icon = icons[channel];
    const line =
        channel === 'delivery'
            ? delivery.from_fee !== null && delivery.minimum_order !== null
                ? t('basket.channel.delivery_line', {
                      fee: formatMoney(delivery.from_fee),
                      minimum: formatMoney(delivery.minimum_order, {
                          compact: true,
                      }),
                  })
                : t('basket.channel.delivery_line_no_zone')
            : channel === 'pickup'
              ? t('basket.channel.pickup_line')
              : t('basket.channel.dine_in_line', { table: table ?? '' });

    return (
        <section
            aria-labelledby="basket-channel"
            className="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4"
        >
            <h2 id="basket-channel" className="text-label text-ink-muted">
                {t('basket.channel.heading')}
            </h2>
            <div className="flex items-start gap-3">
                <span className="grid size-11 shrink-0 place-items-center rounded-full bg-ember-soft text-ember">
                    <Icon className="size-5" aria-hidden="true" />
                </span>
                <div className="min-w-0">
                    <p className="font-semibold text-ink">
                        {t(`basket.channel.${channel}`)}
                    </p>
                    <p className="text-sm text-ink-muted">{line}</p>
                </div>
            </div>
            {channel !== 'dine_in' && (
                <Link
                    href="/checkout"
                    className="inline-flex min-h-11 items-center self-start rounded-sm text-sm font-semibold text-ember underline underline-offset-4 outline-hidden focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid"
                >
                    {t('basket.channel.change')}
                </Link>
            )}
        </section>
    );
}

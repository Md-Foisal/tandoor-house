import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { SentOrder } from './types';

const tone = (status: string) =>
    status === 'ready' || status === 'served' ? 'leaf' : 'turmeric';

export function SentOrders({
    orders,
    token,
}: {
    orders: SentOrder[];
    token: string;
}) {
    const t = useT();

    return (
        <section aria-labelledby="sent-title" className="py-8">
            <h2
                id="sent-title"
                className="font-display text-display-md text-ink"
            >
                {t('table.sent_title')}
            </h2>
            {orders.length === 0 ? (
                <p className="mt-3 max-w-[48ch] text-ink-muted">
                    {t('table.sent_empty')}
                </p>
            ) : (
                <>
                    <ul
                        className="mt-3 divide-y divide-line border-y border-line"
                        aria-live="polite"
                    >
                        {orders.map((order) => (
                            <li
                                key={order.number}
                                className="flex flex-col gap-1 py-4"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <p className="font-semibold text-ink tabular-nums">
                                        {t('table.sent_order', {
                                            number: order.number,
                                        })}
                                    </p>
                                    <Badge variant={tone(order.status)}>
                                        {t(`table.status.${order.status}`)}
                                    </Badge>
                                </div>
                                <p className="text-sm text-ink-muted tabular-nums">
                                    {t('table.sent_at', {
                                        time: order.placed_label,
                                    })}
                                    {' · '}
                                    {formatMoney(order.total)}
                                </p>
                                <p className="text-sm text-ink">
                                    {order.summary}
                                </p>
                            </li>
                        ))}
                    </ul>
                    <Link
                        href={`/t/${token}/bill`}
                        className="mt-3 inline-flex min-h-11 items-center font-semibold text-ember underline underline-offset-4 outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember"
                    >
                        {t('table.help.view_bill')}
                    </Link>
                </>
            )}
        </section>
    );
}

import { Link, router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { Price } from '@/components/brand/price';
import { SectionHeading } from '@/components/brand/section-heading';
import { EmptyState, useOnline } from '@/components/brand/states';
import { Course, Plate } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import type { RecentOrder } from './types';

const shownLines = 3;

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
        timeZone: 'Europe/London',
    }).format(new Date(iso));
}

function OrderSlip({ order }: { order: RecentOrder }) {
    const t = useT();
    const online = useOnline();
    const [sending, setSending] = useState(false);
    const [failed, setFailed] = useState(false);
    const extra = order.lines.length - shownLines;

    const reorder = () => {
        setFailed(false);
        router.post(
            `/account/orders/${order.number}/reorder`,
            {},
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onError: () => setFailed(true),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <article className="flex h-full flex-col gap-4 rounded-lg border border-line bg-surface p-5">
            <header className="flex items-baseline justify-between gap-3">
                <h3 className="text-label text-ink-muted">
                    {t('home.reorder.order_on', {
                        date: formatDate(order.placed_at),
                    })}
                </h3>
                <Price pence={order.total} compact={false} />
            </header>
            <ul className="flex-1 space-y-1 text-ink">
                {order.lines.slice(0, shownLines).map((line, index) => (
                    <li key={`${line.name}-${index}`}>
                        <span className="text-ink-muted tabular-nums">
                            {line.quantity} ×
                        </span>{' '}
                        {line.name}
                    </li>
                ))}
                {extra > 0 && (
                    <li className="text-sm text-ink-muted">
                        {t('home.reorder.more_items', { count: extra })}
                    </li>
                )}
            </ul>
            {failed && (
                <p role="alert" className="text-sm text-chilli">
                    {t('home.reorder.failed')}
                </p>
            )}
            <Button
                variant="outline"
                disabled={sending || !online}
                aria-label={t('home.reorder.action_label', {
                    number: order.number,
                })}
                onClick={reorder}
            >
                <RotateCcw aria-hidden="true" />
                {sending ? t('home.reorder.sending') : t('home.reorder.action')}
            </Button>
        </article>
    );
}

/** Signed-in customers get their last orders; guests get a nudge instead. */
export function QuickReorder({
    orders,
    signedIn,
}: {
    orders: RecentOrder[] | null;
    signedIn: boolean;
}) {
    const t = useT();

    if (!signedIn) {
        return (
            <section
                aria-label={t('home.reorder.title')}
                className="container-page pt-12"
            >
                <p className="flex flex-wrap items-center gap-x-4 gap-y-1 border-y border-line py-4 text-ink-muted">
                    {t('home.reorder.guest')}
                    <Button asChild variant="link">
                        <Link href="/login">
                            {t('home.reorder.guest_action')}
                        </Link>
                    </Button>
                </p>
            </section>
        );
    }

    if (orders === null) {
        return null;
    }

    return (
        <section
            aria-labelledby="reorder-title"
            className="container-page pt-section"
        >
            <SectionHeading
                id="reorder-title"
                size="md"
                eyebrow={t('home.reorder.eyebrow')}
                title={t('home.reorder.title')}
            />
            {orders.length === 0 ? (
                <EmptyState
                    title={t('home.reorder.empty_title')}
                    description={t('home.reorder.empty_body')}
                    action={
                        <Button asChild>
                            <Link href="/menu" prefetch>
                                {t('home.reorder.empty_action')}
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <Course className="mt-8 grid gap-4 md:grid-cols-3">
                    {orders.map((order) => (
                        <Plate key={order.number}>
                            <OrderSlip order={order} />
                        </Plate>
                    ))}
                </Course>
            )}
        </section>
    );
}

import {
    Deferred,
    Head,
    Link,
    router,
    usePage,
    usePoll,
} from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmberLine } from '@/components/brand/ember-line';
import { ErrorState, useOnline } from '@/components/brand/states';
import { Plate } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { OrderDenied } from '@/components/tracking/denied';
import {
    OrderDetails,
    OrderDetailsSkeleton,
} from '@/components/tracking/order-details';
import { StatusCard } from '@/components/tracking/status-card';
import type {
    OrderTrackingProps,
    TrackedDetails,
    TrackedOrder,
} from '@/components/tracking/types';
import { useT } from '@/lib/i18n';

const POLL_MS = 15_000;

type Tracked = Extract<OrderTrackingProps, { denied: false }>;

function Tracker({ number, order, details, can }: Tracked) {
    const t = useT();
    const online = useOnline();
    const { flash } = usePage();
    const [arrived] = useState(
        () =>
            (flash as { toast?: { type?: string } } | undefined)?.toast
                ?.type === 'success',
    );
    const [pollFailed, setPollFailed] = useState(false);
    const [announcement, setAnnouncement] = useState('');
    const [reordering, setReordering] = useState(false);
    const previous = useRef(order.status);

    const poll = usePoll(
        POLL_MS,
        {
            only: ['order'],
            onSuccess: () => setPollFailed(false),
            onError: () => setPollFailed(true),
            onNetworkError: () => setPollFailed(true),
        },
        { autoStart: false },
    );

    useEffect(() => {
        if (order.active && online) {
            poll.start();
        } else {
            poll.stop();
        }

        return poll.stop;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [order.active, online]);

    useEffect(() => {
        if (previous.current !== order.status) {
            previous.current = order.status;
            const label = t(
                order.status === 'ready'
                    ? `tracking.headline.ready_${order.channel}`
                    : `tracking.headline.${order.status}`,
            );
            setAnnouncement(t('tracking.announce', { status: label }));
        }
    }, [order.status, order.channel, t]);

    const finished = !order.terminal && !order.active;

    const reorder = () => {
        setReordering(true);
        router.post(
            `/account/orders/${number}/reorder`,
            {},
            { onFinish: () => setReordering(false) },
        );
    };

    return (
        <>
            <Head title={t('tracking.page_title', { number })} />
            <div className="container-page pt-8 pb-16 md:pt-12">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-semibold tracking-wide text-ember uppercase">
                        {t(`tracking.channel.${order.channel}`)}
                    </p>
                    <h1 className="font-display text-display-lg text-ink">
                        {t('tracking.title', { number })}
                    </h1>
                    <EmberLine />
                    <p className="text-ink-muted tabular-nums">
                        {t('tracking.placed_at', { time: order.placed_label })}
                    </p>
                </header>

                <div className="sr-only" role="status" aria-live="polite">
                    {announcement}
                </div>

                <div className="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_26rem] lg:items-start lg:gap-12">
                    <div className="flex flex-col gap-4">
                        {arrived && order.status === 'placed' && (
                            <Plate className="flex items-start gap-3 rounded-lg border border-leaf/40 bg-leaf-soft p-4">
                                <CheckCircle2
                                    className="mt-0.5 size-6 shrink-0 text-leaf"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p className="font-semibold text-ink">
                                        {t('tracking.arrived_title')}
                                    </p>
                                    <p className="text-ink-muted">
                                        {t('tracking.arrived_body')}
                                    </p>
                                </div>
                            </Plate>
                        )}

                        {!online && order.active && (
                            <p
                                role="status"
                                className="rounded-md border border-line bg-clay px-4 py-3 text-sm text-ink"
                            >
                                {t('tracking.offline')}
                            </p>
                        )}

                        {pollFailed && online && (
                            <ErrorState
                                title={t('tracking.update_failed_title')}
                                onRetry={() =>
                                    router.reload({
                                        only: ['order'],
                                        onSuccess: () => setPollFailed(false),
                                    })
                                }
                            />
                        )}

                        <div
                            aria-label={t('tracking.status_label')}
                            role="region"
                        >
                            <StatusCard order={order as TrackedOrder} />
                        </div>

                        {finished && (
                            <section className="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5">
                                <h2 className="font-display text-display-md text-ink">
                                    {t('tracking.done.title')}
                                </h2>
                                <p className="text-ink-muted">
                                    {t('tracking.done.body')}
                                </p>
                                {(can.review || can.reorder) && (
                                    <div className="flex flex-wrap gap-3 pt-1">
                                        {can.review && (
                                            <Button asChild size="lg">
                                                <Link href="/account/reviews">
                                                    {t('tracking.done.review')}
                                                </Link>
                                            </Button>
                                        )}
                                        {can.reorder && (
                                            <Button
                                                size="lg"
                                                variant="outline"
                                                onClick={reorder}
                                                disabled={!online || reordering}
                                            >
                                                {t('tracking.done.again')}
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </section>
                        )}

                        {order.terminal && can.reorder && (
                            <div>
                                <Button
                                    size="lg"
                                    variant="outline"
                                    onClick={reorder}
                                    disabled={!online || reordering}
                                >
                                    {t('tracking.terminal.try_again')}
                                </Button>
                            </div>
                        )}
                    </div>

                    <section
                        aria-label={t('tracking.details_label')}
                        className="lg:sticky lg:top-24"
                    >
                        <Deferred
                            data="details"
                            fallback={<OrderDetailsSkeleton />}
                        >
                            <OrderDetails
                                details={details as TrackedDetails}
                                channel={order.channel}
                            />
                        </Deferred>
                    </section>
                </div>
            </div>
        </>
    );
}

export default function OrderShow(props: OrderTrackingProps) {
    if (props.denied) {
        return <OrderDenied number={props.number} />;
    }

    return <Tracker {...props} />;
}

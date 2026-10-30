import { usePage } from '@inertiajs/react';
import { Bike, CircleX, Phone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { TicketSteps } from '@/components/brand/ticket-steps';
import { Plate } from '@/components/motion/served';
import { choice, useT } from '@/lib/i18n';
import type { TrackedOrder } from './types';

function useNow(active: boolean): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!active) {
            return;
        }

        const id = window.setInterval(() => setNow(Date.now()), 20_000);

        return () => window.clearInterval(id);
    }, [active]);

    return now;
}

export function StatusCard({ order }: { order: TrackedOrder }) {
    const t = useT();
    const now = useNow(order.active);
    const { restaurant } = usePage().props as {
        restaurant?: { name: string; phone?: string };
    };

    const minutes = Math.ceil(
        (new Date(order.estimated_at).getTime() - now) / 60_000,
    );
    const key = (group: 'headline' | 'step_detail', status: string) =>
        status === 'ready'
            ? `tracking.${group}.ready_${order.channel}`
            : `tracking.${group}.${status}`;

    if (order.terminal) {
        return (
            <section
                aria-labelledby="order-status"
                className="flex flex-col gap-3 rounded-lg border border-chilli/40 bg-chilli-soft p-5"
            >
                <CircleX className="size-8 text-chilli" aria-hidden="true" />
                <h2
                    id="order-status"
                    className="font-display text-display-md text-ink"
                >
                    {t(`tracking.headline.${order.status}`)}
                </h2>
                <p className="text-ink">
                    <span className="font-semibold">
                        {t('tracking.terminal.reason_label')}:
                    </span>{' '}
                    {order.rejection_reason ?? t('tracking.terminal.no_reason')}
                </p>
                <p className="text-ink-muted">
                    {order.refund === 'pending'
                        ? t('tracking.terminal.refund_pending')
                        : order.refund === 'refunded'
                          ? t('tracking.terminal.refund_done')
                          : t('tracking.terminal.no_charge')}
                </p>
            </section>
        );
    }

    const steps = order.steps.map((step) => ({
        key: step,
        label: t(`tracking.steps.${step}`),
        detail: undefined as string | undefined,
    }));
    const current = order.current ?? 0;

    if (current < steps.length) {
        steps[current].detail = t(key('step_detail', steps[current].key));
    }

    return (
        <div className="flex flex-col gap-4">
            <section
                aria-labelledby="order-status"
                className="flex flex-col gap-2 rounded-lg border border-line bg-surface p-5"
            >
                <h2
                    id="order-status"
                    className="font-display text-display-md text-ink"
                >
                    {t(key('headline', order.status))}
                </h2>
                {order.active && (
                    <>
                        {minutes > 0 ? (
                            <>
                                <p className="text-title font-semibold text-ember tabular-nums">
                                    {t(`tracking.estimate.${order.channel}`, {
                                        time: order.estimated_label,
                                    })}
                                </p>
                                <p className="text-ink-muted tabular-nums">
                                    {choice(
                                        t('tracking.estimate.left', {
                                            count: minutes,
                                        }),
                                        minutes,
                                    )}
                                </p>
                            </>
                        ) : (
                            <>
                                <p className="text-title font-semibold text-ink">
                                    {t('tracking.estimate.late')}
                                </p>
                                {restaurant?.phone && (
                                    <a
                                        href={`tel:${restaurant.phone.replace(/\s+/g, '')}`}
                                        className="inline-flex min-h-11 items-center gap-2 self-start rounded-md font-semibold text-ember underline underline-offset-4 outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember"
                                    >
                                        <Phone
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {t('tracking.estimate.late_call')} ·{' '}
                                        {restaurant.phone}
                                    </a>
                                )}
                            </>
                        )}
                    </>
                )}
            </section>

            {order.status === 'out_for_delivery' && order.rider_name && (
                <Plate className="flex items-center gap-3 rounded-lg border border-ember bg-surface p-4">
                    <Bike
                        className="size-7 shrink-0 text-ember"
                        aria-hidden="true"
                    />
                    <div>
                        <p className="font-semibold text-ink">
                            {t('tracking.rider.title')}
                        </p>
                        <p className="text-ink-muted">
                            {t('tracking.rider.body', {
                                name: order.rider_name,
                            })}
                        </p>
                    </div>
                </Plate>
            )}

            <TicketSteps steps={steps} current={current} />
        </div>
    );
}

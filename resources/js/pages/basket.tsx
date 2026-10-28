import { Deferred, Head, Link, router } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { EmberLine } from '@/components/brand/ember-line';
import { EmptyState, ErrorState, useOnline } from '@/components/brand/states';
import { StickyActionBar } from '@/components/brand/sticky-action-bar';
import {
    BasketLine,
    BasketLineSkeleton,
} from '@/components/basket/basket-line';
import { ChannelSummary } from '@/components/basket/channel-summary';
import { PromoForm } from '@/components/basket/promo-form';
import {
    SuggestedSpecials,
    SuggestedSpecialsSkeleton,
} from '@/components/basket/suggested-specials';
import { Totals } from '@/components/basket/totals';
import type {
    BasketLineData,
    BasketPageProps,
} from '@/components/basket/types';
import { Button } from '@/components/ui/button';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { duration, ease } from '@/lib/motion';

export default function Basket({
    basket,
    channels,
    table,
    service_rate: serviceRate,
    delivery,
    specials = [],
}: BasketPageProps) {
    const t = useT();
    const online = useOnline();
    const reduced = useReducedMotion();
    const [optimistic, setOptimistic] = useState<Record<string, number>>({});
    const [failed, setFailed] = useState(false);
    const [reloading, setReloading] = useState(false);

    const empty = basket.lines.length === 0;
    const channel = channels.find((option) => option.value === basket.channel);
    const blocked = empty || !online || basket.has_unavailable;

    const fail = () => {
        setFailed(true);
        toast.error(t('basket.update_failed'));
    };

    const setQuantity = (line: BasketLineData, quantity: number) => {
        setFailed(false);
        setOptimistic((current) => ({ ...current, [line.key]: quantity }));

        router.patch(
            `/basket/lines/${line.key}`,
            { quantity },
            {
                preserveScroll: true,
                preserveState: true,
                onError: fail,
                onFinish: () =>
                    setOptimistic((current) =>
                        current[line.key] === quantity
                            ? Object.fromEntries(
                                  Object.entries(current).filter(
                                      ([key]) => key !== line.key,
                                  ),
                              )
                            : current,
                    ),
            },
        );
    };

    const remove = (line: BasketLineData) => {
        setFailed(false);

        router.delete(`/basket/lines/${line.key}`, {
            preserveScroll: true,
            onError: fail,
            onSuccess: () =>
                toast(t('basket.removed', { item: line.name }), {
                    action: {
                        label: t('basket.undo'),
                        onClick: () =>
                            router.post(
                                '/basket/lines',
                                {
                                    menu_item_id: line.menu_item_id,
                                    item_variant_id: line.item_variant_id,
                                    modifier_ids: line.modifier_ids,
                                    quantity: line.quantity,
                                    notes: line.notes,
                                },
                                { preserveScroll: true },
                            ),
                    },
                }),
        });
    };

    const checkoutLabel = empty
        ? t('basket.checkout_empty')
        : t('basket.checkout', { total: formatMoney(basket.total) });

    const blockReason = !online
        ? t('basket.checkout_offline')
        : basket.has_unavailable
          ? t('basket.sold_out_block')
          : null;

    return (
        <>
            <Head title={t('basket.page_title')} />
            <div className="container-page pt-8 pb-40 md:pt-12 md:pb-16">
                <header className="flex flex-col gap-2">
                    <h1 className="font-display text-display-lg text-ink">
                        {t('basket.title')}
                    </h1>
                    <EmberLine />
                    {!empty && (
                        <p className="text-ink-muted">
                            {choice(
                                t('basket.items', { count: basket.count }),
                                basket.count,
                            )}
                            {' · '}
                            {t('basket.lead')}
                        </p>
                    )}
                </header>

                {empty ? (
                    <div className="mt-6 flex flex-col items-center">
                        <EmptyState
                            title={t('basket.empty_title')}
                            description={t('basket.empty_body')}
                            action={
                                <Button asChild size="lg">
                                    <Link href="/menu">
                                        {t('basket.see_menu')}
                                    </Link>
                                </Button>
                            }
                        />
                        <Deferred
                            data="specials"
                            fallback={<SuggestedSpecialsSkeleton />}
                        >
                            <SuggestedSpecials items={specials} />
                        </Deferred>
                    </div>
                ) : (
                    <div className="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start lg:gap-12">
                        <section aria-label={t('basket.lines_label')}>
                            {failed && (
                                <ErrorState
                                    className="mb-4"
                                    onRetry={() => {
                                        setFailed(false);
                                        setReloading(true);
                                        router.reload({
                                            onFinish: () => setReloading(false),
                                        });
                                    }}
                                />
                            )}
                            <ul className="divide-y divide-line border-y border-line">
                                {reloading ? (
                                    <>
                                        <li>
                                            <BasketLineSkeleton />
                                        </li>
                                        <li>
                                            <BasketLineSkeleton />
                                        </li>
                                    </>
                                ) : (
                                    <AnimatePresence initial={false}>
                                        {basket.lines.map((line) => (
                                            <motion.li
                                                key={line.key}
                                                layout={!reduced}
                                                exit={{ opacity: 0 }}
                                                transition={{
                                                    duration: duration.base,
                                                    ease: ease.out,
                                                }}
                                            >
                                                <BasketLine
                                                    line={line}
                                                    quantity={
                                                        optimistic[line.key] ??
                                                        line.quantity
                                                    }
                                                    onQuantity={(quantity) =>
                                                        setQuantity(
                                                            line,
                                                            quantity,
                                                        )
                                                    }
                                                    onRemove={() =>
                                                        remove(line)
                                                    }
                                                    disabled={!online}
                                                />
                                            </motion.li>
                                        ))}
                                    </AnimatePresence>
                                )}
                            </ul>
                        </section>

                        <aside className="flex flex-col gap-4 lg:sticky lg:top-28">
                            <ChannelSummary
                                channel={basket.channel}
                                table={table}
                                delivery={delivery}
                            />
                            {channel && !channel.available && (
                                <p className="text-sm text-ink-muted">
                                    {t('basket.channel.unavailable')}
                                </p>
                            )}
                            <PromoForm
                                appliedCode={basket.promo_code}
                                invalid={basket.promo_invalid}
                            />
                            <Totals basket={basket} serviceRate={serviceRate} />
                            {basket.has_alcohol && (
                                <p className="text-sm text-ink-muted">
                                    {t('basket.alcohol_note')}
                                </p>
                            )}
                            {blockReason && (
                                <p
                                    role="alert"
                                    className="text-sm font-medium text-chilli"
                                >
                                    {blockReason}
                                </p>
                            )}
                            <StickyActionBar>
                                {blocked ? (
                                    <Button
                                        size="lg"
                                        className="w-full"
                                        disabled
                                    >
                                        {checkoutLabel}
                                    </Button>
                                ) : (
                                    <Button
                                        asChild
                                        size="lg"
                                        className="w-full"
                                    >
                                        <Link href="/checkout">
                                            {checkoutLabel}
                                        </Link>
                                    </Button>
                                )}
                            </StickyActionBar>
                        </aside>
                    </div>
                )}
            </div>
        </>
    );
}

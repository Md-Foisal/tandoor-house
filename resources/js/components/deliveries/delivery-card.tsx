import { router } from '@inertiajs/react';
import {
    Banknote,
    CreditCard,
    IdCard,
    MapPin,
    Navigation,
    Package,
    Phone,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { Elapsed } from '@/components/staff/elapsed';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { DeliverDialog, FailDialog } from './actions-dialogs';
import { mapsUrl, type DeliveryCardData, type Rider } from './types';

export function DeliveryCard({
    card,
    manages,
    riders,
}: {
    card: DeliveryCardData;
    manages: boolean;
    riders: Rider[];
}) {
    const t = useT();
    const online = useOnline();
    const [busy, setBusy] = useState(false);
    const [dialog, setDialog] = useState<'deliver' | 'fail' | null>(null);
    const done = card.status === 'delivered' || card.status === 'completed';
    const offline = !online ? t('staff_deliveries.actions.offline') : undefined;

    const pickUp = () => {
        setBusy(true);
        router.post(
            `/staff/deliveries/${card.number}/pickup`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['deliveries', 'cash'],
                onFinish: () => setBusy(false),
            },
        );
    };

    const assign = (value: string) => {
        router.patch(
            `/staff/deliveries/${card.number}/rider`,
            { rider_id: value === 'none' ? null : Number(value) },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['deliveries'],
            },
        );
    };

    return (
        <article
            className="grid gap-4 rounded-lg border-2 border-ink/80 bg-surface p-4"
            aria-label={card.number}
        >
            <header className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-label text-ink-muted uppercase">
                        {card.number}
                    </p>
                    <h2 className="font-display text-display-md">
                        {card.first_name}
                    </h2>
                </div>
                {!done && card.ready_at && (
                    <p className="shrink-0 text-right text-sm text-ink-muted">
                        {t('staff_deliveries.card.ready_for')}{' '}
                        <Elapsed
                            since={card.ready_at}
                            warnAfter={8}
                            lateAfter={15}
                            className="text-lg"
                        />
                    </p>
                )}
            </header>

            <div
                className={cn(
                    'flex items-center gap-3 rounded-md border-2 px-4 py-3 text-xl font-bold',
                    card.cash_due
                        ? 'border-ink bg-turmeric-soft text-ink'
                        : 'border-leaf bg-leaf-soft text-ink',
                )}
            >
                {card.cash_due ? (
                    <Banknote className="size-6 shrink-0" aria-hidden="true" />
                ) : (
                    <CreditCard
                        className="size-6 shrink-0"
                        aria-hidden="true"
                    />
                )}
                {card.cash_due
                    ? t('staff_deliveries.card.collect_cash', {
                          amount: formatMoney(card.total),
                      })
                    : card.cash_collected !== null
                      ? t('staff_deliveries.card.cash_taken', {
                            amount: formatMoney(card.cash_collected),
                        })
                      : t('staff_deliveries.card.paid_card')}
            </div>

            {card.age_check && (
                <p className="flex items-center gap-2 rounded-md bg-ink px-4 py-3 font-bold text-paper">
                    <IdCard className="size-5 shrink-0" aria-hidden="true" />
                    {t('staff_deliveries.card.check_id')}
                </p>
            )}

            <div className="grid gap-1">
                <p className="flex items-start gap-2 text-lg font-semibold">
                    <MapPin
                        className="mt-1 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <span>
                        {card.address}
                        {card.postcode && (
                            <>
                                {', '}
                                <span className="whitespace-nowrap">
                                    {card.postcode}
                                </span>
                            </>
                        )}
                    </span>
                </p>
                {card.instructions && (
                    <p className="ml-7 rounded-md bg-clay px-3 py-2 text-sm">
                        <span className="font-semibold">
                            {t('staff_deliveries.card.instructions')}:
                        </span>{' '}
                        {card.instructions}
                    </p>
                )}
                {card.last_failure && (
                    <p className="ml-7 flex items-start gap-2 text-sm font-semibold text-chilli">
                        <TriangleAlert
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {t('staff_deliveries.card.failed_before', {
                            reason: card.last_failure,
                        })}
                    </p>
                )}
            </div>

            <p className="flex items-center gap-2 text-ink-muted">
                <Package className="size-5" aria-hidden="true" />
                {choice(t('staff_deliveries.card.items'), card.items).replace(
                    ':count',
                    String(card.items),
                )}
                {' · '}
                {choice(t('staff_deliveries.card.bags'), card.bags).replace(
                    ':count',
                    String(card.bags),
                )}
            </p>

            {!done && (
                <div className="grid grid-cols-2 gap-2">
                    {(['google', 'apple'] as const).map((kind) => (
                        <Button
                            key={kind}
                            asChild
                            variant="outline"
                            className="min-h-12"
                        >
                            <a
                                href={mapsUrl(kind, card)}
                                target="_blank"
                                rel="noreferrer"
                                aria-label={`${t('staff_deliveries.navigate.label', { address: card.address })} (${t(`staff_deliveries.navigate.${kind}`)})`}
                            >
                                <Navigation aria-hidden="true" />
                                {t(`staff_deliveries.navigate.${kind}`)}
                            </a>
                        </Button>
                    ))}
                </div>
            )}

            {card.phone && !done && (
                <Button
                    asChild
                    variant="secondary"
                    className="min-h-14 text-lg"
                >
                    <a href={`tel:${card.phone.replace(/\s+/g, '')}`}>
                        <Phone aria-hidden="true" />
                        {card.phone}
                    </a>
                </Button>
            )}

            {manages && (
                <div className="grid gap-1">
                    <p className="text-sm text-ink-muted">
                        {card.rider
                            ? t('staff_deliveries.card.rider', {
                                  name: card.rider.name,
                              })
                            : t('staff_deliveries.card.no_rider')}
                    </p>
                    {!done && (
                        <Select
                            value={card.rider ? String(card.rider.id) : 'none'}
                            onValueChange={assign}
                            disabled={!online}
                        >
                            <SelectTrigger
                                className="min-h-12"
                                aria-label={t('staff_deliveries.assign.label', {
                                    number: card.number,
                                })}
                            >
                                <SelectValue
                                    placeholder={t(
                                        'staff_deliveries.assign.placeholder',
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    {t('staff_deliveries.assign.none')}
                                </SelectItem>
                                {riders.map((rider) => (
                                    <SelectItem
                                        key={rider.id}
                                        value={String(rider.id)}
                                    >
                                        {rider.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>
            )}

            {card.status === 'ready' && (
                <Button
                    size="lg"
                    className="min-h-14 text-lg"
                    disabled={busy || !online}
                    title={offline}
                    onClick={pickUp}
                >
                    {t('staff_deliveries.actions.picked_up')}
                </Button>
            )}

            {card.status === 'out_for_delivery' && (
                <div className="grid gap-2">
                    <Button
                        size="lg"
                        className="min-h-14 text-lg"
                        disabled={!online}
                        title={offline}
                        onClick={() => setDialog('deliver')}
                    >
                        {t('staff_deliveries.actions.delivered')}
                    </Button>
                    <Button
                        variant="outline"
                        className="min-h-12"
                        disabled={!online}
                        title={offline}
                        onClick={() => setDialog('fail')}
                    >
                        {t('staff_deliveries.actions.failed')}
                    </Button>
                </div>
            )}

            {!online && !done && (
                <p className="text-sm font-semibold text-chilli" role="status">
                    {t('staff_deliveries.actions.offline')}
                </p>
            )}

            <DeliverDialog
                card={card}
                open={dialog === 'deliver'}
                onClose={() => setDialog(null)}
            />
            <FailDialog
                card={card}
                open={dialog === 'fail'}
                onClose={() => setDialog(null)}
            />
        </article>
    );
}

export function DeliveryCardSkeleton() {
    return (
        <div
            className="grid gap-4 rounded-lg border-2 border-line bg-surface p-4"
            aria-hidden="true"
        >
            <Skeleton className="h-8 w-1/2" />
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-14 w-full" />
        </div>
    );
}

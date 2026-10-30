import { Head, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { EmptyState, useOnline } from '@/components/brand/states';
import { CashSummaryPanel } from '@/components/deliveries/cash-summary';
import {
    DeliveryCard,
    DeliveryCardSkeleton,
} from '@/components/deliveries/delivery-card';
import type {
    CashSummary,
    DeliveryCardData,
    DeliveryTab,
    Rider,
} from '@/components/deliveries/types';
import { StaffPageHeader } from '@/components/staff/page-header';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const tabs: DeliveryTab[] = ['ready', 'road', 'done'];

type Props = {
    tab: DeliveryTab;
    manages: boolean;
    deliveries: Record<DeliveryTab, DeliveryCardData[]>;
    cash: CashSummary;
    riders: Rider[];
};

export default function Deliveries({
    tab: initialTab,
    manages,
    deliveries,
    cash,
    riders,
}: Props) {
    const t = useT();
    const online = useOnline();
    const [tab, setTab] = useState<DeliveryTab>(initialTab);
    const [stale, setStale] = useState(false);
    const poll = usePoll(
        15_000,
        { only: ['deliveries', 'cash'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (online) {
            poll.start();
        } else {
            poll.stop();
        }

        return poll.stop;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [online]);

    useEffect(() => {
        const off = [
            router.on('httpException', () => setStale(true)),
            router.on('networkError', () => setStale(true)),
            router.on('success', () => setStale(false)),
        ];

        return () => off.forEach((fn) => fn());
    }, []);

    const choose = (next: DeliveryTab) => {
        setTab(next);
        window.history.replaceState(
            window.history.state,
            '',
            next === 'ready' ? '/staff/deliveries' : `?tab=${next}`,
        );
    };

    const list = deliveries?.[tab];
    const loading = list === undefined;

    return (
        <>
            <Head title={t('staff_deliveries.title')} />
            <StaffPageHeader
                title={t('staff_deliveries.title')}
                description={`${t(manages ? 'staff_deliveries.description_manager' : 'staff_deliveries.description_rider')} ${t('staff_deliveries.live')}`}
            />

            {stale && (
                <div
                    role="alert"
                    className="border-b border-chilli/40 bg-chilli-soft px-4 py-2 text-sm text-chilli"
                >
                    <strong>{t('staff_deliveries.error.title')}</strong>{' '}
                    {t('staff_deliveries.error.body')}
                </div>
            )}

            <div className="sticky top-0 z-10 border-b border-line bg-paper px-4 py-2 md:px-6">
                <div
                    role="tablist"
                    aria-label={t('staff_deliveries.title')}
                    className="flex gap-2 overflow-x-auto pb-1"
                >
                    {tabs.map((key) => (
                        <button
                            key={key}
                            id={`tab-${key}`}
                            role="tab"
                            type="button"
                            aria-selected={tab === key}
                            aria-controls="runs"
                            onClick={() => choose(key)}
                            className={cn(
                                'inline-flex min-h-12 shrink-0 items-center gap-2 rounded-full border-2 px-4 text-sm font-bold',
                                tab === key
                                    ? 'border-ink bg-ink text-paper'
                                    : 'border-line-strong bg-surface text-ink',
                            )}
                        >
                            {t(`staff_deliveries.tabs.${key}`)}
                            <span className="tabular-nums">
                                {deliveries?.[key]?.length ?? 0}
                            </span>
                        </button>
                    ))}
                </div>
            </div>

            <div
                id="runs"
                role="tabpanel"
                aria-labelledby={`tab-${tab}`}
                className="mx-auto grid max-w-5xl gap-4 p-4 md:p-6"
            >
                {tab === 'done' && (
                    <CashSummaryPanel cash={cash} manages={manages} />
                )}
                {loading ? (
                    <>
                        <DeliveryCardSkeleton />
                        <DeliveryCardSkeleton />
                    </>
                ) : list.length === 0 ? (
                    <EmptyState
                        title={t('staff_deliveries.empty.title')}
                        description={t(`staff_deliveries.empty.${tab}`)}
                    />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {list.map((card) => (
                            <DeliveryCard
                                key={card.number}
                                card={card}
                                manages={manages}
                                riders={riders}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

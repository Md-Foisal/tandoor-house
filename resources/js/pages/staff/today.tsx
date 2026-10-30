import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ErrorState, useOnline } from '@/components/brand/states';
import { Front, FrontSkeleton } from '@/components/staff-today/front';
import { Kitchen, KitchenSkeleton } from '@/components/staff-today/kitchen';
import {
    Management,
    ManagementSkeleton,
} from '@/components/staff-today/management';
import { Rider, RiderSkeleton } from '@/components/staff-today/rider';
import { ShiftCard } from '@/components/staff-today/shift-card';
import type {
    FrontBlocks,
    KitchenBlocks,
    ManagementBlocks,
    RiderBlocks,
    ShiftInfo,
    TodayView,
} from '@/components/staff-today/types';
import { StaffPageHeader } from '@/components/staff/page-header';
import { Badge } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';
import { channels, events, useLive } from '@/lib/realtime';

type TodayProps = {
    first_name: string;
    date: string;
    server_time: string;
    open: { isOpen: boolean; until: string | null; opensAt: string | null };
    periods: string[];
    view: TodayView;
    shift: ShiftInfo;
    blocks?: ManagementBlocks | FrontBlocks | KitchenBlocks | RiderBlocks;
};

const POLL_MS = 15_000;
const PROPS = ['server_time', 'open', 'shift', 'blocks'];

const skeletons = {
    management: <ManagementSkeleton />,
    front: <FrontSkeleton />,
    kitchen: <KitchenSkeleton />,
    rider: <RiderSkeleton />,
};

export default function Today({
    first_name: firstName,
    date,
    server_time: serverTime,
    open,
    periods,
    view,
    shift,
    blocks,
}: TodayProps) {
    const t = useT();
    const online = useOnline();
    const { errors } = usePage().props;
    const [failed, setFailed] = useState(false);

    const riderId = usePage().props.auth.user?.id ?? null;

    useLive(PROPS, {
        channel:
            view === 'rider' && riderId !== null
                ? channels.rider(riderId)
                : channels.staffOrders,
        events:
            view === 'rider'
                ? [events.orderStatusChanged, events.riderAssigned]
                : [events.orderPlaced, events.orderStatusChanged],
        fallbackMs: POLL_MS,
    });

    useEffect(() => {
        const offs = [
            router.on('success', () => setFailed(false)),
            router.on('networkError', () => setFailed(true)),
            router.on('httpException', () => setFailed(true)),
        ];

        return () => offs.forEach((off) => off());
    }, []);

    const weekday = new Date(serverTime).toLocaleDateString('en-GB', {
        weekday: 'long',
        timeZone: 'Europe/London',
    });

    const status = open.isOpen
        ? t('staff_today.open.open', { time: open.until ?? '' })
        : open.opensAt
          ? t('staff_today.open.opens_at', { when: open.opensAt })
          : t('staff_today.open.closed');

    const retry = () => router.reload({ only: PROPS });

    return (
        <>
            <Head title={t('staff.areas.today')} />
            <StaffPageHeader
                title={t('staff_today.greeting', { name: firstName })}
                description={date}
                actions={
                    <Badge variant={open.isOpen ? 'leaf' : 'secondary'}>
                        {status}
                    </Badge>
                }
            >
                <p className="text-sm text-ink-muted">
                    {periods.length > 0
                        ? `${t('staff_today.open.periods')}: ${periods.join(', ')}`
                        : t('staff_today.open.no_service')}
                </p>
            </StaffPageHeader>

            <div className="space-y-4 p-4 md:p-6">
                {!online && (
                    <p
                        role="status"
                        className="flex items-center gap-2 rounded-lg border border-line bg-clay px-4 py-3 text-sm"
                    >
                        <WifiOff className="size-4" aria-hidden />
                        {t('staff_today.offline')}
                    </p>
                )}
                {failed && online && (
                    <div className="flex flex-wrap items-center gap-3">
                        <ErrorState
                            title={t('staff_today.updated_failed')}
                            onRetry={retry}
                        />
                    </div>
                )}

                <ShiftCard info={shift} error={errors?.clock} />

                <Deferred data="blocks" fallback={skeletons[view]}>
                    {blocks && view === 'management' && (
                        <Management
                            blocks={blocks as ManagementBlocks}
                            weekday={weekday}
                        />
                    )}
                    {blocks && view === 'front' && (
                        <Front blocks={blocks as FrontBlocks} />
                    )}
                    {blocks && view === 'kitchen' && (
                        <Kitchen blocks={blocks as KitchenBlocks} />
                    )}
                    {blocks && view === 'rider' && (
                        <Rider blocks={blocks as RiderBlocks} />
                    )}
                </Deferred>
            </div>
        </>
    );
}

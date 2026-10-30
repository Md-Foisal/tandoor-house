import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { Link2, WifiOff, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { EmptyState, useOnline } from '@/components/brand/states';
import { BookingsRail } from '@/components/floor/bookings-rail';
import { FloorMap } from '@/components/floor/floor-map';
import { FloorLoading } from '@/components/floor/floor-skeleton';
import { StatusLabel, statusOrder } from '@/components/floor/status';
import { TableList } from '@/components/floor/table-list';
import { TableSheet } from '@/components/floor/table-sheet';
import type {
    FloorBooking,
    FloorProps,
    FloorTable,
} from '@/components/floor/types';
import { useFloorActions } from '@/components/floor/use-floor-actions';
import { useNewIds } from '@/components/floor/use-new-ids';
import { useNow } from '@/components/floor/use-now';
import { StaffPageHeader } from '@/components/staff/page-header';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useT } from '@/lib/i18n';
import { channels, events, useLive } from '@/lib/realtime';

const POLL_MS = 10_000;
const STALE_MS = 35_000;

function initialArea(url: string): string | null {
    return new URL(url, 'http://local').searchParams.get('area');
}

export default function Floor({ tables, upcoming }: FloorProps) {
    const t = useT();
    const online = useOnline();
    const { url } = usePage();
    const [lastOk, setLastOk] = useState(() => Date.now());
    const [selecting, setSelecting] = useState(false);
    const [picked, setPicked] = useState<Set<number>>(new Set());
    const actions = useFloorActions();

    const { live } = useLive(['tables', 'upcoming'], {
        channel: channels.staffFloor,
        events: [
            events.tableRequestRaised,
            events.tableStatusChanged,
            events.orderStatusChanged,
        ],
        fallbackMs: POLL_MS,
    });

    useEffect(() => router.on('success', () => setLastOk(Date.now())), []);

    const now = useNow(5_000);
    // A quiet live socket sends nothing, so only a silent poll can be stale.
    const stale = online && !live && now - lastOk > STALE_MS;
    const initial = useMemo(() => initialArea(url), [url]);

    const toggleSelecting = () => {
        setSelecting((on) => !on);
        setPicked(new Set());
    };

    return (
        <>
            <Head title={t('staff_floor.title')} />
            <StaffPageHeader
                title={t('staff_floor.title')}
                description={
                    selecting
                        ? t('staff_floor.toolbar.merge_hint')
                        : t('staff_floor.description')
                }
                actions={
                    tables && tables.length > 0 ? (
                        selecting ? (
                            <>
                                <Button
                                    size="lg"
                                    disabled={
                                        picked.size < 2 ||
                                        actions.busy ||
                                        !online
                                    }
                                    onClick={() =>
                                        actions.merge([...picked], () => {
                                            setSelecting(false);
                                            setPicked(new Set());
                                        })
                                    }
                                >
                                    <Link2 aria-hidden />
                                    {t('staff_floor.toolbar.merge_confirm', {
                                        count: picked.size,
                                    })}
                                </Button>
                                <Button
                                    size="lg"
                                    variant="outline"
                                    onClick={toggleSelecting}
                                >
                                    <X aria-hidden />
                                    {t('staff_floor.toolbar.merge_cancel')}
                                </Button>
                            </>
                        ) : (
                            <Button
                                size="lg"
                                variant="outline"
                                onClick={toggleSelecting}
                            >
                                <Link2 aria-hidden />
                                {t('staff_floor.toolbar.merge')}
                            </Button>
                        )
                    ) : undefined
                }
            >
                <ul
                    aria-label={t('staff_floor.legend')}
                    className="flex flex-wrap gap-x-4 gap-y-1 text-sm"
                >
                    {statusOrder.map((status) => (
                        <li key={status}>
                            <StatusLabel status={status} />
                        </li>
                    ))}
                </ul>
            </StaffPageHeader>

            <div className="flex flex-col gap-4 px-4 py-4 md:px-6">
                {!online && (
                    <p
                        role="status"
                        className="flex items-center gap-2 rounded-md bg-turmeric-soft p-3 text-sm font-semibold text-turmeric"
                    >
                        <WifiOff className="size-4 shrink-0" aria-hidden />
                        {t('staff_floor.states.offline')}
                    </p>
                )}
                {stale && (
                    <div
                        role="alert"
                        className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-chilli-soft p-3 text-sm font-semibold text-chilli"
                    >
                        {t('staff_floor.states.stale', {
                            minutes: Math.max(
                                1,
                                Math.round((now - lastOk) / 60_000),
                            ),
                        })}
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.reload({
                                    only: ['tables', 'upcoming'],
                                    onSuccess: () => setLastOk(Date.now()),
                                })
                            }
                        >
                            {t('staff_floor.states.retry')}
                        </Button>
                    </div>
                )}

                <Deferred
                    data={['tables', 'upcoming']}
                    fallback={<FloorLoading />}
                >
                    <FloorBoard
                        tables={tables ?? []}
                        upcoming={upcoming ?? []}
                        initialArea={initial}
                        online={online}
                        actions={actions}
                        selecting={selecting}
                        picked={picked}
                        setPicked={setPicked}
                    />
                </Deferred>
            </div>
        </>
    );
}

function FloorBoard({
    tables,
    upcoming,
    initialArea,
    online,
    actions,
    selecting,
    picked,
    setPicked,
}: {
    tables: FloorTable[];
    upcoming: FloorBooking[];
    initialArea: string | null;
    online: boolean;
    actions: ReturnType<typeof useFloorActions>;
    selecting: boolean;
    picked: Set<number>;
    setPicked: (next: Set<number>) => void;
}) {
    const t = useT();
    const [openId, setOpenId] = useState<number | null>(null);
    const [areaName, setAreaName] = useState<string | null>(initialArea);

    const areas = useMemo(() => {
        const grouped = new Map<string, FloorTable[]>();

        tables.forEach((table) => {
            grouped.set(table.area, [
                ...(grouped.get(table.area) ?? []),
                table,
            ]);
        });

        return [...grouped].map(([name, list]) => ({ name, tables: list }));
    }, [tables]);

    const requestIds = tables.flatMap((table) =>
        table.requests.map((request) => request.id),
    );
    const fresh = useNewIds(requestIds);

    const current =
        areas.find((area) => area.name === areaName) ?? areas[0] ?? null;
    const open = tables.find((table) => table.id === openId) ?? null;

    const isSelectable = (table: FloorTable) =>
        table.merged_into_id === null &&
        (table.status === 'free' || table.status === 'seated');

    const activate = (table: FloorTable) => {
        if (!selecting) {
            setOpenId(table.id);

            return;
        }

        if (!isSelectable(table)) {
            return;
        }

        const next = new Set(picked);

        if (!next.delete(table.id)) {
            next.add(table.id);
        }

        setPicked(next);
    };

    const chooseArea = (name: string) => {
        setAreaName(name);
        const params = new URLSearchParams(window.location.search);
        params.set('area', name);
        window.history.replaceState(
            window.history.state,
            '',
            `${window.location.pathname}?${params.toString()}`,
        );
    };

    if (areas.length === 0) {
        return (
            <EmptyState
                title={t('staff_floor.areas.empty_title', {
                    area: t('staff_floor.areas.all'),
                })}
                description={t('staff_floor.areas.empty_body')}
            />
        );
    }

    return (
        <>
            <div className="flex flex-col gap-6 md:gap-8 lg:flex-row lg:items-start">
                <div className="min-w-0 flex-1">
                    <div className="md:hidden">
                        <TableList
                            areas={areas}
                            fresh={fresh}
                            selecting={selecting}
                            selectedIds={picked}
                            isSelectable={isSelectable}
                            onActivate={activate}
                        />
                    </div>

                    <div className="hidden flex-col gap-4 md:flex">
                        <Tabs value={current?.name} onValueChange={chooseArea}>
                            <TabsList
                                aria-label={t('staff_floor.areas.label')}
                                className="h-auto w-full flex-wrap justify-start gap-1"
                            >
                                {areas.map((area) => {
                                    const free = area.tables.filter(
                                        (table) => table.status === 'free',
                                    ).length;
                                    const seated = area.tables.filter(
                                        (table) =>
                                            table.status === 'seated' ||
                                            table.status === 'bill_requested',
                                    ).length;
                                    const waiting = area.tables.some(
                                        (table) => table.requests.length > 0,
                                    );

                                    return (
                                        <TabsTrigger
                                            key={area.name}
                                            value={area.name}
                                            aria-controls={undefined}
                                            className="min-h-12 flex-col items-start gap-0 px-4"
                                        >
                                            <span className="font-semibold">
                                                {area.name}
                                                {waiting && (
                                                    <span
                                                        aria-hidden
                                                        className="ml-1.5 inline-block size-2 rounded-full bg-chilli align-middle"
                                                    />
                                                )}
                                            </span>
                                            <span className="text-xs font-normal text-ink-muted">
                                                {t(
                                                    'staff_floor.areas.summary',
                                                    {
                                                        free,
                                                        seated,
                                                    },
                                                )}
                                            </span>
                                        </TabsTrigger>
                                    );
                                })}
                            </TabsList>
                        </Tabs>
                        {current && current.tables.length > 0 ? (
                            <>
                                <FloorMap
                                    tables={current.tables}
                                    fresh={fresh}
                                    selecting={selecting}
                                    selectedIds={picked}
                                    isSelectable={isSelectable}
                                    onActivate={activate}
                                    label={current.name}
                                />
                                <p className="text-sm text-ink-muted">
                                    {t('staff_floor.toolbar.keys')}
                                </p>
                            </>
                        ) : (
                            <EmptyState
                                title={t('staff_floor.areas.empty_title', {
                                    area: current?.name ?? '',
                                })}
                                description={t('staff_floor.areas.empty_body')}
                            />
                        )}
                    </div>
                </div>

                <BookingsRail
                    className="order-first md:order-last lg:w-80 lg:shrink-0"
                    bookings={upcoming}
                    tables={tables}
                    actions={actions}
                    online={online}
                />
            </div>

            <TableSheet
                table={open}
                tables={tables}
                actions={actions}
                online={online}
                onClose={() => setOpenId(null)}
                onOpenTable={setOpenId}
            />
        </>
    );
}

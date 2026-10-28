import { Head, router, usePoll } from '@inertiajs/react';
import { Moon, Sun, UtensilsCrossed } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { AllDayPanel } from '@/components/kitchen/all-day-panel';
import { RecentRail } from '@/components/kitchen/recent-rail';
import { StationTabs } from '@/components/kitchen/station-tabs';
import { TicketCard } from '@/components/kitchen/ticket-card';
import { TicketSkeleton } from '@/components/kitchen/ticket-skeleton';
import type {
    AllDayRow,
    KitchenLine,
    KitchenTicket,
    RecentLine,
    StationTab,
} from '@/components/kitchen/types';
import { EmptyState, ErrorState, useOnline } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { setKitchenTheme } from '@/layouts/kitchen-layout';
import { useT } from '@/lib/i18n';

type KitchenProps = {
    station: string;
    stations: StationTab[];
    tickets: KitchenTicket[];
    all_day: AllDayRow[];
    recent: RecentLine[];
    server_time: string;
};

const POLL_MS = 5000;
const PROPS = [
    'station',
    'stations',
    'tickets',
    'all_day',
    'recent',
    'server_time',
];
const STATION_KEY = 'kitchen-station';
const BASE = '/staff/kitchen';

function readStoredStation(): string | null {
    try {
        return localStorage.getItem(STATION_KEY);
    } catch {
        return null;
    }
}

function useClock(): string {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(new Date()), 10_000);

        return () => window.clearInterval(timer);
    }, []);

    return now.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Europe/London',
    });
}

export default function Kitchen({
    station,
    stations,
    tickets,
    all_day: allDay,
    recent,
}: KitchenProps) {
    const t = useT();
    const online = useOnline();
    const clock = useClock();
    const [selected, setSelected] = useState(0);
    const [switching, setSwitching] = useState(false);
    const [failed, setFailed] = useState(false);
    const [busy, setBusy] = useState(false);
    const [allDayOpen, setAllDayOpen] = useState(false);
    const [soldOut, setSoldOut] = useState<AllDayRow | null>(null);
    const [dark, setDark] = useState(() =>
        typeof document === 'undefined'
            ? true
            : document.documentElement.classList.contains('dark'),
    );

    const poll = usePoll(POLL_MS, { only: PROPS }, { autoStart: false });

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
        const offs = [
            router.on('success', () => setFailed(false)),
            router.on('networkError', () => setFailed(true)),
            router.on('httpException', () => setFailed(true)),
        ];

        return () => offs.forEach((off) => off());
    }, []);

    const choose = useCallback((next: string) => {
        try {
            localStorage.setItem(STATION_KEY, next);
        } catch {
            // The choice then lasts only for this visit.
        }

        setSelected(0);
        setSwitching(true);
        router.get(BASE, next === 'all' ? {} : { station: next }, {
            only: PROPS,
            replace: true,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setSwitching(false),
        });
    }, []);

    const asked = useRef(false);

    useEffect(() => {
        if (asked.current) {
            return;
        }

        asked.current = true;
        const stored = readStoredStation();
        const hasParam = new URLSearchParams(window.location.search).has(
            'station',
        );

        if (
            !hasParam &&
            stored &&
            stored !== 'all' &&
            stored !== station &&
            stations.some((tab) => tab.value === stored)
        ) {
            choose(stored);
        }
    }, [choose, station, stations]);

    const stationLabel =
        stations.find((tab) => tab.value === station)?.label ?? '';
    const locked = !online || busy;

    const act = useCallback(
        (url: string, data: Record<string, string> = {}) => {
            setBusy(true);
            router.post(url, data, {
                only: PROPS,
                preserveScroll: true,
                preserveState: true,
                onError: () => toast.error(t('staff_kitchen.bump_failed')),
                onFinish: () => setBusy(false),
            });
        },
        [t],
    );

    const bumpLine = (line: KitchenLine) =>
        act(`${BASE}/lines/${line.id}/bump`);
    const bumpTicket = useCallback(
        (ticket: KitchenTicket) =>
            act(`${BASE}/orders/${ticket.id}/bump`, { station }),
        [act, station],
    );
    const recall = useCallback(
        (line: RecentLine) => act(`${BASE}/lines/${line.id}/recall`),
        [act],
    );

    const changeTheme = () => {
        const next = dark ? 'light' : 'dark';

        setKitchenTheme(next);
        setDark(next === 'dark');
    };

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (
                event.metaKey ||
                event.ctrlKey ||
                event.altKey ||
                allDayOpen ||
                soldOut ||
                target?.closest('input, textarea, select, [role="dialog"]')
            ) {
                return;
            }

            if (/^[1-9]$/.test(event.key)) {
                const index = Number(event.key) - 1;

                if (index < tickets.length) {
                    setSelected(index);
                }
            } else if (event.key === 'Enter' && !locked) {
                const ticket = tickets[selected];

                if (ticket && target?.tagName !== 'BUTTON') {
                    event.preventDefault();
                    bumpTicket(ticket);
                }
            } else if (
                event.key.toLowerCase() === 'r' &&
                !locked &&
                recent[0]
            ) {
                recall(recent[0]);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [
        tickets,
        selected,
        recent,
        locked,
        allDayOpen,
        soldOut,
        bumpTicket,
        recall,
    ]);

    const confirmSoldOut = () => {
        if (!soldOut?.menu_item_id) {
            return;
        }

        router.post(
            `${BASE}/items/${soldOut.menu_item_id}/sold-out`,
            {},
            {
                only: PROPS,
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSoldOut(null),
            },
        );
    };

    const selectedIndex = Math.min(selected, Math.max(tickets.length - 1, 0));

    return (
        <>
            <Head title={t('staff_kitchen.title')} />

            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-3 py-2">
                <StationTabs
                    stations={stations}
                    active={station}
                    onSelect={choose}
                />
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        className="h-12"
                        onClick={() => setAllDayOpen(true)}
                    >
                        <UtensilsCrossed aria-hidden="true" />
                        {t('staff_kitchen.all_day.title')}
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-12"
                        onClick={changeTheme}
                        aria-label={t(
                            dark
                                ? 'staff_kitchen.theme.light'
                                : 'staff_kitchen.theme.dark',
                        )}
                    >
                        {dark ? (
                            <Sun aria-hidden="true" />
                        ) : (
                            <Moon aria-hidden="true" />
                        )}
                    </Button>
                    <time
                        aria-label={t('staff_kitchen.clock')}
                        className="min-w-16 text-right font-display text-2xl tabular-nums"
                    >
                        {clock}
                    </time>
                </div>
            </div>

            {!online && (
                <p
                    role="status"
                    className="border-b border-line bg-turmeric-soft px-4 py-2 text-sm font-semibold"
                >
                    {t('staff_kitchen.offline')}
                </p>
            )}

            <div className="min-h-0 flex-1 overflow-y-auto p-3">
                {failed && online && (
                    <ErrorState
                        className="mb-3"
                        title={t('staff_kitchen.error.title')}
                        onRetry={() =>
                            router.reload({
                                only: PROPS,
                                onSuccess: () => setFailed(false),
                            })
                        }
                    />
                )}

                {switching ? (
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {[0, 1, 2].map((key) => (
                            <TicketSkeleton key={key} />
                        ))}
                    </div>
                ) : tickets.length === 0 ? (
                    <EmptyState
                        title={t('staff_kitchen.empty.title')}
                        description={
                            station === 'all'
                                ? t('staff_kitchen.empty.body')
                                : t('staff_kitchen.empty.station_body', {
                                      station: stationLabel,
                                  })
                        }
                    />
                ) : (
                    <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {tickets.map((ticket, index) => (
                            <TicketCard
                                key={ticket.id}
                                ticket={ticket}
                                position={index}
                                selected={index === selectedIndex}
                                stationLabel={stationLabel}
                                stationValue={station}
                                disabled={locked}
                                onBumpLine={bumpLine}
                                onBumpTicket={bumpTicket}
                            />
                        ))}
                    </div>
                )}
                {!online && tickets.length > 0 && (
                    <p className="mt-3 text-sm text-ink-muted">
                        {t('staff_kitchen.ticket.offline_reason')}
                    </p>
                )}
                <p className="mt-4 hidden text-xs text-ink-muted md:block">
                    {t('staff_kitchen.shortcuts.title')}:{' '}
                    {t('staff_kitchen.shortcuts.select')} ·{' '}
                    {t('staff_kitchen.shortcuts.bump')} ·{' '}
                    {t('staff_kitchen.shortcuts.recall')}
                </p>
            </div>

            <RecentRail lines={recent} disabled={locked} onRecall={recall} />

            <AllDayPanel
                open={allDayOpen}
                onOpenChange={setAllDayOpen}
                rows={allDay}
                disabled={!online}
                onSoldOut={setSoldOut}
            />

            <Dialog
                open={soldOut !== null}
                onOpenChange={(open) => !open && setSoldOut(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('staff_kitchen.sold_out.title', {
                                dish: soldOut?.name ?? '',
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('staff_kitchen.sold_out.body')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            className="h-12"
                            onClick={() => setSoldOut(null)}
                        >
                            {t('staff_kitchen.sold_out.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            className="h-12"
                            onClick={confirmSoldOut}
                        >
                            {t('staff_kitchen.sold_out.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

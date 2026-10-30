import { Head, router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { EmptyState } from '@/components/brand/states';
import { StaffPageHeader } from '@/components/staff/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import {
    OrderCard,
    OrderCardSkeleton,
} from '@/components/staff-orders/order-card';
import {
    boardColumns,
    type BoardColumnKey,
    type OrderCardData,
} from '@/components/staff-orders/types';
import { useT } from '@/lib/i18n';
import { channels as liveChannels, events, useLive } from '@/lib/realtime';
import { cn } from '@/lib/utils';
import type { OrderChannel, OrderStatus } from '@/types';

type Filters = {
    channel: OrderChannel | null;
    status: OrderStatus | null;
    q: string;
};

const POLL_MS = 5_000;
const CHIME_KEY = 'th-orders-chime';
const statuses: OrderStatus[] = [
    'placed',
    'accepted',
    'cooking',
    'ready',
    'out_for_delivery',
    'served',
    'collected',
    'delivered',
    'completed',
    'rejected',
    'cancelled',
];
const channels: OrderChannel[] = ['dine_in', 'pickup', 'delivery'];

function readChime(): boolean {
    try {
        return window.localStorage.getItem(CHIME_KEY) === '1';
    } catch {
        return false;
    }
}

function chime() {
    try {
        const context = new window.AudioContext();
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.setValueAtTime(0.0001, context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.2, context.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(
            0.0001,
            context.currentTime + 0.5,
        );
        oscillator.connect(gain).connect(context.destination);
        oscillator.start();
        oscillator.stop(context.currentTime + 0.5);
        oscillator.onended = () => void context.close();
    } catch {
        // No audio on this device: the highlight is enough.
    }
}

export default function OrderBoard({
    orders,
    filters,
}: {
    orders: OrderCardData[];
    filters: Filters;
}) {
    const t = useT();
    const searchRef = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState(filters.q);
    const [active, setActive] = useState<BoardColumnKey>('new');
    const [loading, setLoading] = useState(false);
    const [stale, setStale] = useState(false);
    const [chimeOn, setChimeOn] = useState(false);
    const [fresh, setFresh] = useState<string[]>([]);
    const seen = useRef<Set<string> | null>(null);
    const chimeRef = useRef(chimeOn);
    chimeRef.current = chimeOn;

    useLive(['orders'], {
        channel: liveChannels.staffOrders,
        events: [
            events.orderPlaced,
            events.orderStatusChanged,
            events.riderAssigned,
        ],
        fallbackMs: POLL_MS,
    });

    useEffect(() => {
        setChimeOn(readChime());
    }, []);

    useEffect(() => {
        const offFail = [
            router.on('httpException', () => setStale(true)),
            router.on('networkError', () => setStale(true)),
            router.on('success', () => setStale(false)),
        ];

        return () => offFail.forEach((off) => off());
    }, []);

    // A new order is highlighted once for a few seconds; the chime is opt-in.
    useEffect(() => {
        const numbers = new Set(orders.map((order) => order.number));

        if (seen.current !== null) {
            const added = orders.filter(
                (order) =>
                    order.status === 'placed' &&
                    !seen.current?.has(order.number),
            );

            if (added.length > 0) {
                setFresh((current) => [
                    ...current,
                    ...added.map((o) => o.number),
                ]);
                window.setTimeout(
                    () =>
                        setFresh((current) =>
                            current.filter(
                                (n) => !added.some((o) => o.number === n),
                            ),
                        ),
                    8_000,
                );

                if (chimeRef.current) {
                    chime();
                }
            }
        }

        seen.current = numbers;
    }, [orders]);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (
                event.key === '/' &&
                !['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName ?? '')
            ) {
                event.preventDefault();
                searchRef.current?.focus();
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const apply = (next: Partial<Filters>) => {
        const merged = { ...filters, q: query, ...next };
        const params: Record<string, string> = {};

        if (merged.channel) params.channel = merged.channel;
        if (merged.status) params.status = merged.status;
        if (merged.q) params.q = merged.q;

        router.get('/staff/orders', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['orders', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const applyRef = useRef(apply);
    applyRef.current = apply;

    useEffect(() => {
        if (query === filters.q) {
            return;
        }

        const timer = window.setTimeout(
            () => applyRef.current({ q: query }),
            300,
        );

        return () => window.clearTimeout(timer);
    }, [query, filters.q]);

    const grouped = useMemo(() => {
        const byColumn = new Map<BoardColumnKey, OrderCardData[]>();

        for (const column of boardColumns) {
            const items = orders.filter((order) =>
                column.statuses.includes(order.status),
            );
            byColumn.set(column.key, items);
        }

        return byColumn;
    }, [orders]);

    const filtered = Boolean(filters.channel || filters.status || filters.q);
    const clear = () => {
        setQuery('');
        apply({ channel: null, status: null, q: '' });
    };

    return (
        <>
            <Head title={t('staff_orders.title')} />
            <StaffPageHeader
                title={t('staff_orders.title')}
                description={t('staff_orders.description')}
                actions={
                    <div className="flex items-center gap-3">
                        <Label
                            htmlFor="chime"
                            className="flex min-h-11 cursor-pointer items-center gap-2 text-sm"
                        >
                            <Switch
                                id="chime"
                                checked={chimeOn}
                                aria-describedby="chime-hint"
                                onCheckedChange={(on) => {
                                    setChimeOn(on);

                                    try {
                                        window.localStorage.setItem(
                                            CHIME_KEY,
                                            on ? '1' : '0',
                                        );
                                    } catch {
                                        // Not saved on this device; still works for this visit.
                                    }

                                    if (on) chime();
                                }}
                            />
                            {t('staff_orders.chime')}
                        </Label>
                        <span id="chime-hint" className="sr-only">
                            {t('staff_orders.chime_hint')}
                        </span>
                    </div>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-0 flex-1 basis-60">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted"
                            aria-hidden="true"
                        />
                        <Input
                            ref={searchRef}
                            type="search"
                            inputMode="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={t('staff_orders.search')}
                            aria-label={t('staff_orders.search')}
                            aria-keyshortcuts="/"
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={filters.channel ?? 'all'}
                        onValueChange={(value) =>
                            apply({
                                channel:
                                    value === 'all'
                                        ? null
                                        : (value as OrderChannel),
                            })
                        }
                    >
                        <SelectTrigger
                            className="min-h-11 w-44"
                            aria-label={t('staff_orders.filters.channel')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                {t('staff_orders.filters.all_channels')}
                            </SelectItem>
                            {channels.map((channel) => (
                                <SelectItem key={channel} value={channel}>
                                    {t(`staff.channel.${channel}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            apply({
                                status:
                                    value === 'all'
                                        ? null
                                        : (value as OrderStatus),
                            })
                        }
                    >
                        <SelectTrigger
                            className="min-h-11 w-44"
                            aria-label={t('staff_orders.filters.status')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                {t('staff_orders.filters.all_statuses')}
                            </SelectItem>
                            {statuses.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {t(`staff.status.${status}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {filtered && (
                        <Button variant="ghost" onClick={clear}>
                            <X aria-hidden="true" />
                            {t('staff_orders.filters.clear')}
                        </Button>
                    )}
                </div>
                <p className="mt-2 hidden text-sm text-ink-muted md:block">
                    {t('staff_orders.search_hint')} · {t('staff_orders.live')}
                </p>
            </StaffPageHeader>

            {stale && (
                <div
                    role="alert"
                    className="border-b border-chilli/40 bg-chilli-soft px-4 py-2 text-sm text-chilli md:px-6"
                >
                    <strong>{t('staff_orders.error.title')}</strong>{' '}
                    {t('staff_orders.error.body')}
                </div>
            )}

            <div className="sticky top-0 z-10 border-b border-line bg-paper px-4 py-2 md:hidden">
                <div
                    role="tablist"
                    aria-label={t('staff_orders.title')}
                    className="flex gap-2 overflow-x-auto pb-1"
                >
                    {boardColumns.map((column) => (
                        <button
                            key={column.key}
                            role="tab"
                            type="button"
                            aria-selected={active === column.key}
                            onClick={() => setActive(column.key)}
                            className={cn(
                                'inline-flex min-h-11 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-semibold',
                                active === column.key
                                    ? 'border-ink bg-ink text-paper'
                                    : 'border-line-strong bg-surface text-ink',
                            )}
                        >
                            {t(`staff_orders.columns.${column.key}`)}
                            <span className="tabular-nums">
                                {grouped.get(column.key)?.length ?? 0}
                            </span>
                        </button>
                    ))}
                </div>
            </div>

            <div className="p-4 md:p-6" aria-busy={loading}>
                {orders.length === 0 && !loading ? (
                    <EmptyState
                        title={
                            filtered
                                ? t('staff_orders.empty.search_title')
                                : t('staff_orders.empty.board_title')
                        }
                        description={
                            filtered
                                ? t('staff_orders.empty.search_body')
                                : t('staff_orders.empty.board_body')
                        }
                        action={
                            filtered ? (
                                <Button variant="outline" onClick={clear}>
                                    {t('staff_orders.filters.clear')}
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="grid gap-4 md:auto-cols-[minmax(17rem,1fr)] md:grid-flow-col md:overflow-x-auto md:pb-2">
                        {boardColumns.map((column) => {
                            const items = grouped.get(column.key) ?? [];

                            return (
                                <section
                                    key={column.key}
                                    aria-labelledby={`col-${column.key}`}
                                    className={cn(
                                        'min-w-0 flex-col gap-3',
                                        active === column.key
                                            ? 'flex'
                                            : 'hidden',
                                        'md:flex',
                                    )}
                                >
                                    <h2
                                        id={`col-${column.key}`}
                                        className="hidden items-center justify-between border-b-2 border-ink pb-2 text-label text-ink uppercase md:flex"
                                    >
                                        {t(
                                            `staff_orders.columns.${column.key}`,
                                        )}
                                        <span className="rounded-full bg-ink px-2 py-0.5 text-paper tabular-nums">
                                            {items.length}
                                        </span>
                                    </h2>
                                    {loading ? (
                                        <OrderCardSkeleton />
                                    ) : items.length === 0 ? (
                                        <p className="rounded-lg border border-dashed border-line-strong px-4 py-8 text-center text-sm text-ink-muted">
                                            {t('staff_orders.empty.column')}
                                        </p>
                                    ) : (
                                        items.map((order) => (
                                            <OrderCard
                                                key={order.number}
                                                order={order}
                                                highlight={fresh.includes(
                                                    order.number,
                                                )}
                                            />
                                        ))
                                    )}
                                </section>
                            );
                        })}
                    </div>
                )}
            </div>
            <p className="sr-only" aria-live="polite">
                {fresh.length > 0 &&
                    t('staff_orders.new_order', {
                        number: fresh[fresh.length - 1] ?? '',
                    })}
            </p>
        </>
    );
}

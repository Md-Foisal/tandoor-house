import { Users } from 'lucide-react';
import { Fragment, useEffect, useMemo, useRef } from 'react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { OccasionIcon } from './booking-badges';
import type { Booking, BoardTable, Period } from './types';
import { minutesToTime, timeToMinutes } from './types';

const COLUMN_MINUTES = 15;
const COLUMN_PX = 28;
const LABEL_PX = 104;
const ROW_PX = 60;
const HEADER_PX = 36;

const blockTone: Record<Booking['status'], string> = {
    pending_deposit: 'border-dashed border-turmeric bg-turmeric-soft text-ink',
    confirmed: 'border-ember bg-ember-soft text-ink',
    seated: 'border-leaf bg-leaf-soft text-ink',
    completed: 'border-line-strong bg-clay text-ink-muted',
    no_show: 'border-chilli bg-chilli-soft text-ink-muted line-through',
    cancelled: 'border-line bg-clay text-ink-muted',
};

type Row = {
    key: string;
    label: string;
    seats: number | null;
    table: number | null;
};

/**
 * Tables down the side, time across the top in 15 minute columns. A booking
 * is a block as long as the meal. Closed hours between services are shaded.
 */
export function Timeline({
    tables,
    periods,
    bookings,
    nowMinute,
    isToday,
    highlighted,
    onSelect,
}: {
    tables: BoardTable[];
    periods: Period[];
    bookings: Booking[];
    nowMinute: number;
    isToday: boolean;
    highlighted: Set<number>;
    onSelect: (id: number) => void;
}) {
    const t = useT();
    const scroller = useRef<HTMLDivElement>(null);

    const { from, to } = useMemo(() => {
        const starts = [
            ...periods.map((p) => timeToMinutes(p.start)),
            ...bookings.map((b) => b.start_minute),
        ];
        const ends = [
            ...periods.map((p) => timeToMinutes(p.end)),
            ...bookings.map((b) => b.end_minute),
        ];

        return {
            from: Math.floor(Math.min(...starts) / 60) * 60,
            to: Math.ceil(Math.max(...ends) / 60) * 60,
        };
    }, [periods, bookings]);

    const px = (minute: number) =>
        ((minute - from) / COLUMN_MINUTES) * COLUMN_PX;
    const width = px(to);

    const gaps = useMemo(() => {
        const open = periods
            .map((p) => [timeToMinutes(p.start), timeToMinutes(p.end)] as const)
            .sort((a, b) => a[0] - b[0]);
        const bands: Array<[number, number]> = [];
        let cursor = from;

        for (const [start, end] of open) {
            if (start > cursor) {
                bands.push([cursor, start]);
            }
            cursor = Math.max(cursor, end);
        }
        if (cursor < to) {
            bands.push([cursor, to]);
        }

        return bands;
    }, [periods, from, to]);

    const rows: Array<{ area: string; rows: Row[] }> = useMemo(() => {
        const groups: Array<{ area: string; rows: Row[] }> = [];
        const unassigned = bookings.some((b) => b.dining_table_id === null);

        if (unassigned) {
            groups.push({
                area: t('staff_reservations.timeline.unassigned'),
                rows: [{ key: 'none', label: '-', seats: null, table: null }],
            });
        }

        for (const table of tables) {
            let group = groups.find((g) => g.area === table.area);

            if (!group) {
                group = { area: table.area, rows: [] };
                groups.push(group);
            }

            group.rows.push({
                key: String(table.id),
                label: table.label,
                seats: table.seats,
                table: table.id,
            });
        }

        return groups;
    }, [tables, bookings, t]);

    useEffect(() => {
        if (isToday && scroller.current) {
            scroller.current.scrollLeft = Math.max(
                0,
                px(nowMinute) - scroller.current.clientWidth / 3,
            );
        }
        // Only on first paint: later polls must not yank the view around.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const hours: number[] = [];
    for (let m = from; m < to; m += 60) {
        hours.push(m);
    }

    const showNow = isToday && nowMinute >= from && nowMinute <= to;

    return (
        <div
            ref={scroller}
            role="region"
            aria-label={t('staff_reservations.timeline.label')}
            tabIndex={0}
            className="overflow-x-auto rounded-lg border border-line bg-surface focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid"
        >
            <div
                className="relative"
                style={{ width: LABEL_PX + width, minWidth: '100%' }}
            >
                <div
                    className="sticky top-0 z-20 flex border-b border-line bg-paper"
                    style={{ height: HEADER_PX }}
                >
                    <div
                        className="sticky left-0 z-30 shrink-0 border-r border-line bg-paper"
                        style={{ width: LABEL_PX }}
                    />
                    <div className="relative" style={{ width }}>
                        {hours.map((m) => (
                            <span
                                key={m}
                                className="absolute top-2 -translate-x-1/2 text-xs font-medium text-ink-muted tabular-nums"
                                style={{ left: px(m) }}
                            >
                                {minutesToTime(m)}
                            </span>
                        ))}
                    </div>
                </div>

                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute bottom-0"
                    style={{ left: LABEL_PX, top: HEADER_PX, width }}
                >
                    {gaps.map(([a, b]) => (
                        <div
                            key={a}
                            className="absolute inset-y-0 bg-clay/70"
                            style={{ left: px(a), width: px(b) - px(a) }}
                        />
                    ))}
                    {hours.map((m) => (
                        <div
                            key={m}
                            className="absolute inset-y-0 border-l border-line"
                            style={{ left: px(m) }}
                        />
                    ))}
                </div>

                {rows.map((group) => (
                    <Fragment key={group.area}>
                        <div className="sticky left-0 z-10 w-fit px-3 py-1.5 text-label font-semibold tracking-wide text-ink-muted uppercase">
                            {group.area}
                        </div>
                        {group.rows.map((row) => (
                            <div
                                key={row.key}
                                className="relative flex border-t border-line"
                                style={{ height: ROW_PX }}
                            >
                                <div
                                    className="sticky left-0 z-10 flex shrink-0 flex-col justify-center border-r border-line bg-surface px-3"
                                    style={{ width: LABEL_PX }}
                                >
                                    <span className="font-semibold">
                                        {row.label}
                                    </span>
                                    {row.seats !== null && (
                                        <span className="flex items-center gap-1 text-xs text-ink-muted">
                                            <Users
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            {row.seats}
                                        </span>
                                    )}
                                </div>
                                <div className="relative" style={{ width }}>
                                    {bookings
                                        .filter(
                                            (b) =>
                                                b.dining_table_id === row.table,
                                        )
                                        .map((b) => (
                                            <Fragment key={b.id}>
                                                <span
                                                    id={`booking-${b.id}-block`}
                                                    className="sr-only"
                                                >
                                                    {t(
                                                        'staff_reservations.timeline.block',
                                                        {
                                                            name: b.name,
                                                            count: b.party_size,
                                                            start: b.start,
                                                            end: b.end,
                                                            status: t(
                                                                `staff_reservations.status.${b.status}`,
                                                            ),
                                                        },
                                                    )}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        onSelect(b.id)
                                                    }
                                                    aria-describedby={`booking-${b.id}-block`}
                                                    className={cn(
                                                        'absolute top-1.5 bottom-1.5 flex min-w-0 flex-col justify-center overflow-hidden rounded-md border-l-4 px-2 text-left text-sm outline-hidden focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ember focus-visible:outline-solid',
                                                        blockTone[b.status],
                                                        highlighted.has(b.id) &&
                                                            'ring-2 ring-ember ring-offset-1 ring-offset-surface',
                                                    )}
                                                    style={{
                                                        left:
                                                            px(b.start_minute) +
                                                            1,
                                                        width:
                                                            px(b.end_minute) -
                                                            px(b.start_minute) -
                                                            2,
                                                    }}
                                                >
                                                    <span className="truncate font-semibold">
                                                        {b.name}
                                                    </span>
                                                    <span className="flex items-center gap-1.5 truncate text-xs">
                                                        <Users
                                                            className="size-3 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                        {b.party_size}
                                                        <OccasionIcon
                                                            occasion={
                                                                b.occasion
                                                            }
                                                        />
                                                        {b.deposit > 0 && (
                                                            <span className="truncate">
                                                                {t(
                                                                    b.deposit_status ===
                                                                        'paid'
                                                                        ? 'staff_reservations.deposit.short_paid'
                                                                        : 'staff_reservations.deposit.short_pending',
                                                                )}
                                                            </span>
                                                        )}
                                                    </span>
                                                </button>
                                            </Fragment>
                                        ))}
                                </div>
                            </div>
                        ))}
                    </Fragment>
                ))}

                {showNow && (
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute bottom-0 z-20 w-0.5 bg-chilli"
                        style={{
                            left: LABEL_PX + px(nowMinute),
                            top: HEADER_PX,
                        }}
                    >
                        <span className="absolute -top-0.5 left-1/2 -translate-x-1/2 rounded-sm bg-chilli px-1 text-[0.6875rem] font-semibold whitespace-nowrap text-ember-ink">
                            {minutesToTime(Math.floor(nowMinute))}
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}

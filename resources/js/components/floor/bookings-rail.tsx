import { CalendarClock } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { FloorBooking, FloorTable } from './types';
import type { FloorActions } from './use-floor-actions';
import { formatClock, useNow } from './use-now';

const selectClass =
    'h-12 w-full rounded-md border border-line-strong bg-surface px-3 text-base text-ink outline-hidden focus-visible:outline-2 focus-visible:outline-ember disabled:opacity-50';

/** Bookings due in the next two hours, with a one-tap way to seat them. */
export function BookingsRail({
    bookings,
    tables,
    actions,
    online,
    className,
}: {
    bookings: FloorBooking[];
    tables: FloorTable[];
    actions: FloorActions;
    online: boolean;
    className?: string;
}) {
    const t = useT();

    return (
        <aside
            aria-labelledby="bookings-heading"
            className={cn('flex flex-col gap-3', className)}
        >
            <h2
                id="bookings-heading"
                className="flex items-center gap-2 font-display text-display-md"
            >
                <CalendarClock className="size-5 text-ember" aria-hidden />
                {t('staff_floor.bookings.heading')}
            </h2>
            {bookings.length === 0 ? (
                <p className="rounded-md bg-clay p-3 text-sm text-ink-muted">
                    {t('staff_floor.bookings.empty')}
                </p>
            ) : (
                <ul className="flex flex-col gap-3">
                    {bookings.map((booking) => (
                        <li key={booking.id}>
                            <BookingCard
                                booking={booking}
                                tables={tables}
                                actions={actions}
                                online={online}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </aside>
    );
}

function BookingCard({
    booking,
    tables,
    actions,
    online,
}: {
    booking: FloorBooking;
    tables: FloorTable[];
    actions: FloorActions;
    online: boolean;
}) {
    const t = useT();
    const now = useNow();
    const [pick, setPick] = useState('');

    const assigned = tables.find((table) => table.id === booking.table_id);
    const assignedReady =
        assigned !== undefined &&
        (assigned.status === 'free' ||
            (assigned.status === 'reserved' &&
                assigned.booking?.id === booking.id));
    const options = tables.filter(
        (table) =>
            table.status === 'free' &&
            table.merged_into_id === null &&
            table.group_seats >= booking.party_size,
    );

    const minutes = Math.round(
        (new Date(booking.starts_at).getTime() - now) / 60_000,
    );
    const when =
        minutes > 0
            ? t('staff_floor.bookings.in', { minutes })
            : minutes < -1
              ? t('staff_floor.bookings.late', { minutes: -minutes })
              : t('staff_floor.bookings.now');

    const needsPick = !assignedReady;
    const tableId = assignedReady ? assigned.id : Number(pick) || null;
    const blocked = booking.deposit_pending;

    return (
        <div className="flex flex-col gap-2 rounded-md border border-line-strong bg-surface p-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="truncate font-semibold">{booking.name}</p>
                    <p className="text-sm text-ink-muted">
                        {t('staff_floor.bookings.party', {
                            count: booking.party_size,
                        })}
                        {' · '}
                        {booking.table_label
                            ? t('staff_floor.bookings.table', {
                                  table: booking.table_label,
                              })
                            : t('staff_floor.bookings.no_table')}
                    </p>
                    {booking.occasion && (
                        <p className="text-sm text-ink-muted">
                            {t('staff_floor.bookings.occasion', {
                                occasion: booking.occasion,
                            })}
                        </p>
                    )}
                </div>
                <div className="text-right">
                    <p className="font-semibold tabular-nums">
                        {formatClock(booking.starts_at)}
                    </p>
                    <p
                        className={cn(
                            'text-sm tabular-nums',
                            minutes < -1
                                ? 'font-semibold text-chilli'
                                : 'text-ink-muted',
                        )}
                    >
                        {when}
                    </p>
                </div>
            </div>
            {blocked ? (
                <p className="text-sm font-semibold text-turmeric">
                    {t('staff_floor.bookings.deposit')}
                </p>
            ) : (
                <>
                    {needsPick &&
                        (options.length === 0 ? (
                            <p className="text-sm text-ink-muted">
                                {t('staff_floor.bookings.no_free')}
                            </p>
                        ) : (
                            <select
                                className={selectClass}
                                aria-label={t(
                                    'staff_floor.bookings.pick_table',
                                )}
                                value={pick}
                                onChange={(event) =>
                                    setPick(event.target.value)
                                }
                            >
                                <option value="">
                                    {t('staff_floor.bookings.pick_table')}
                                </option>
                                {options.map((table) => (
                                    <option key={table.id} value={table.id}>
                                        {table.label} · {table.group_seats} ·{' '}
                                        {table.area}
                                    </option>
                                ))}
                            </select>
                        ))}
                    <Button
                        size="lg"
                        disabled={actions.busy || !online || tableId === null}
                        onClick={() => actions.seatBooking(booking.id, tableId)}
                    >
                        {t('staff_floor.bookings.seat_now')}
                    </Button>
                </>
            )}
        </div>
    );
}

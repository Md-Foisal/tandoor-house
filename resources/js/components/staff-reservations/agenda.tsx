import { Users } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { OccasionIcon, StatusBadge, useDepositLabel } from './booking-badges';
import type { Booking } from './types';

/** Phone layout: the same bookings as a list by start time. */
export function Agenda({
    bookings,
    highlighted,
    onSelect,
}: {
    bookings: Booking[];
    highlighted: Set<number>;
    onSelect: (id: number) => void;
}) {
    const t = useT();
    const depositLabel = useDepositLabel();
    const times = [...new Set(bookings.map((b) => b.start))];

    return (
        <div aria-label={t('staff_reservations.agenda.label')} role="region">
            {times.map((time) => (
                <section key={time} className="mb-4">
                    <h3 className="sticky top-0 z-10 bg-paper py-1 font-display text-title tabular-nums">
                        {time}
                    </h3>
                    <ul className="flex flex-col gap-2">
                        {bookings
                            .filter((b) => b.start === time)
                            .map((b) => (
                                <li key={b.id}>
                                    <button
                                        type="button"
                                        onClick={() => onSelect(b.id)}
                                        className={cn(
                                            'flex min-h-14 w-full flex-col gap-1 rounded-lg border border-line bg-surface p-3 text-left outline-hidden focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid',
                                            highlighted.has(b.id) &&
                                                'ring-2 ring-ember',
                                            (b.status === 'completed' ||
                                                b.status === 'no_show') &&
                                                'bg-clay',
                                        )}
                                    >
                                        <span className="flex items-start justify-between gap-2">
                                            <span className="font-semibold">
                                                {b.name}
                                            </span>
                                            <StatusBadge status={b.status} />
                                        </span>
                                        <span className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-muted">
                                            <span className="flex items-center gap-1">
                                                <Users
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {t(
                                                    'staff_reservations.detail.party',
                                                    { count: b.party_size },
                                                )}
                                            </span>
                                            <span>
                                                {b.table_label
                                                    ? t(
                                                          'staff_reservations.agenda.table',
                                                          {
                                                              table: b.table_label,
                                                          },
                                                      )
                                                    : t(
                                                          'staff_reservations.agenda.no_table',
                                                      )}
                                            </span>
                                            {b.occasion && (
                                                <span className="flex items-center gap-1">
                                                    <OccasionIcon
                                                        occasion={b.occasion}
                                                    />
                                                    {t(
                                                        `staff_reservations.occasion.${b.occasion}`,
                                                    )}
                                                </span>
                                            )}
                                            {depositLabel(b) && (
                                                <span>{depositLabel(b)}</span>
                                            )}
                                        </span>
                                    </button>
                                </li>
                            ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}

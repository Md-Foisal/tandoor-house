import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { WeekDay } from './types';

/** Seven days with covers and bookings; choosing one opens its day view. */
export function WeekView({
    days,
    selected,
    onOpen,
}: {
    days: WeekDay[];
    selected: string;
    onOpen: (date: string) => void;
}) {
    const t = useT();

    return (
        <ul
            aria-label={t('staff_reservations.week.label')}
            className="grid grid-cols-1 gap-2 md:grid-cols-7"
        >
            {days.map((day) => (
                <li key={day.date}>
                    <button
                        type="button"
                        onClick={() => onOpen(day.date)}
                        aria-label={t('staff_reservations.week.open_day', {
                            day: `${day.weekday} ${day.day} ${day.month}`,
                        })}
                        aria-current={day.is_today ? 'date' : undefined}
                        className={cn(
                            'flex min-h-16 w-full items-center justify-between gap-3 rounded-lg border bg-surface p-3 text-left outline-hidden hover:bg-clay focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid md:min-h-40 md:flex-col md:items-start md:justify-start',
                            day.is_today
                                ? 'border-ember'
                                : day.date === selected
                                  ? 'border-line-strong'
                                  : 'border-line',
                            day.closed && 'bg-clay/60 text-ink-muted',
                        )}
                    >
                        <span className="flex items-baseline gap-2 md:flex-col md:gap-0">
                            <span className="text-label font-semibold tracking-wide uppercase">
                                {day.weekday}
                            </span>
                            <span className="font-display text-title tabular-nums">
                                {day.day} {day.month}
                            </span>
                        </span>
                        {day.closed ? (
                            <span className="text-sm">
                                {t('staff_reservations.week.closed')}
                            </span>
                        ) : (
                            <span className="text-right md:mt-auto md:text-left">
                                <span className="block font-display text-display-md tabular-nums">
                                    {day.covers}
                                </span>
                                <span className="block text-sm text-ink-muted">
                                    {t('staff_reservations.week.covers', {
                                        count: day.covers,
                                    })}
                                </span>
                                <span className="block text-sm text-ink-muted">
                                    {t('staff_reservations.week.bookings', {
                                        count: day.bookings,
                                    })}
                                </span>
                            </span>
                        )}
                    </button>
                </li>
            ))}
        </ul>
    );
}

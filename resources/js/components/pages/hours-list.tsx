import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { WeekDay } from './types';

export function HoursList({ hours }: { hours: WeekDay[] }) {
    const t = useT();

    return (
        <ul className="divide-y divide-line border-y border-line">
            {hours.map((day) => (
                <li
                    key={day.day}
                    aria-current={day.isToday ? 'date' : undefined}
                    className={cn(
                        'flex min-h-11 items-center justify-between gap-4 px-2 py-2.5 tabular-nums',
                        day.isToday
                            ? 'rounded-sm bg-turmeric-soft font-semibold text-ink'
                            : 'text-ink-muted',
                    )}
                >
                    <span>
                        {day.day}
                        {day.isToday && (
                            <span className="ml-2 text-label text-turmeric">
                                {t('pages.visit.hours.today')}
                            </span>
                        )}
                    </span>
                    <span className="text-right">
                        {day.periods.length === 0
                            ? t('pages.visit.hours.closed')
                            : day.periods.join(', ')}
                    </span>
                </li>
            ))}
        </ul>
    );
}

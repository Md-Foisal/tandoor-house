import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Day } from './types';

function parts(date: string) {
    const when = new Date(`${date}T12:00:00Z`);
    const format = (options: Intl.DateTimeFormatOptions) =>
        new Intl.DateTimeFormat('en-GB', {
            timeZone: 'UTC',
            ...options,
        }).format(when);

    return {
        weekday: format({ weekday: 'short' }),
        day: format({ day: 'numeric' }),
        month: format({ month: 'short' }),
        long: format({ weekday: 'long', day: 'numeric', month: 'long' }),
    };
}

/** The next sixty days as a scrolling strip; closed days stay visible but disabled. */
export function DatePicker({
    days,
    value,
    onChange,
    error,
}: {
    days: Day[];
    value: string;
    onChange: (date: string) => void;
    error?: string;
}) {
    const t = useT();
    const strip = useRef<HTMLDivElement>(null);

    const scroll = (direction: 1 | -1) =>
        strip.current?.scrollBy({
            left: direction * strip.current.clientWidth * 0.8,
            behavior: 'smooth',
        });

    return (
        <fieldset className="grid min-w-0 grid-cols-1 gap-3">
            <legend className="mb-1 text-title font-semibold">
                <span className="text-ember">2. </span>
                {t('booking.date.legend')}
            </legend>
            <div className="relative min-w-0">
                <div
                    ref={strip}
                    className="-mx-4 flex snap-x scroll-px-4 gap-2 overflow-x-auto px-4 pt-1 pb-3 md:mx-0 md:px-0"
                    role="presentation"
                >
                    {days.map((day, index) => {
                        const shown = parts(day.date);
                        const disabled = day.status !== 'open';
                        const note =
                            day.status === 'closed'
                                ? t('booking.date.closed')
                                : day.status === 'late'
                                  ? t('booking.date.late')
                                  : index === 0
                                    ? t('booking.date.today')
                                    : index === 1
                                      ? t('booking.date.tomorrow')
                                      : shown.weekday;

                        return (
                            <label
                                key={day.date}
                                className="relative shrink-0 snap-start"
                            >
                                <input
                                    type="radio"
                                    name="date"
                                    value={day.date}
                                    checked={value === day.date}
                                    disabled={disabled}
                                    onChange={() => onChange(day.date)}
                                    aria-label={`${shown.long}${disabled ? `, ${note}` : ''}`}
                                    className="peer sr-only"
                                />
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'grid h-20 w-[4.5rem] cursor-pointer place-items-center content-center gap-0.5 rounded-md border border-line-strong bg-surface text-ink transition-colors duration-150',
                                        'peer-checked:border-ember peer-checked:bg-ember peer-checked:text-ember-ink hover:bg-clay',
                                        'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ember',
                                        'peer-disabled:cursor-not-allowed peer-disabled:border-line peer-disabled:bg-clay peer-disabled:text-ink-muted peer-disabled:hover:bg-clay',
                                    )}
                                >
                                    <span className="text-[0.6875rem] font-semibold tracking-[0.08em] uppercase">
                                        {note}
                                    </span>
                                    <span className="text-2xl leading-none font-semibold tabular-nums">
                                        {shown.day}
                                    </span>
                                    <span className="text-xs">
                                        {shown.month}
                                    </span>
                                </span>
                            </label>
                        );
                    })}
                </div>
                <div className="mt-1 hidden items-center justify-between gap-3 md:flex">
                    <p className="text-sm text-ink-muted">
                        {t('booking.date.hint')}
                    </p>
                    <div className="flex gap-1">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label={t('booking.date.earlier')}
                            onClick={() => scroll(-1)}
                        >
                            <ChevronLeft aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label={t('booking.date.scroll')}
                            onClick={() => scroll(1)}
                        >
                            <ChevronRight aria-hidden="true" />
                        </Button>
                    </div>
                </div>
                <p className="text-sm text-ink-muted md:hidden">
                    {t('booking.date.hint')}
                </p>
            </div>
            {error && (
                <p className="text-sm font-medium text-chilli">{error}</p>
            )}
        </fieldset>
    );
}

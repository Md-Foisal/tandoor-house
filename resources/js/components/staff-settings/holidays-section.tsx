import { router } from '@inertiajs/react';
import { CalendarPlus, X } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { choice, useT } from '@/lib/i18n';
import type { SettingsContext } from './types';

const formatter = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

const label = (date: string) => formatter.format(new Date(`${date}T00:00:00Z`));

export function HolidaysSection({
    dates,
    nextYear,
    context,
}: {
    dates: string[];
    nextYear: { year: number; dates: string[] };
    context: SettingsContext;
}) {
    const t = useT();
    const [value, setValue] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const today = new Date().toISOString().slice(0, 10);
    const missing = nextYear.dates.some((d) => !dates.includes(d));

    const save = (next: string[]) => {
        setBusy(true);
        router.put(
            '/staff/settings/holidays',
            { dates: [...new Set(next)].sort() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setValue('');
                    setError(null);
                },
                onError: (errors) => setError(Object.values(errors)[0] ?? null),
                onFinish: () => setBusy(false),
            },
        );
    };

    const disabled = busy || !context.online;

    return (
        <section className="grid gap-5 rounded-lg border border-line bg-surface p-4 md:p-6">
            <div>
                <h2 className="font-display text-display-md">
                    {t('staff_settings.sections.holidays.title')}
                </h2>
                <p className="mt-1 max-w-prose text-ink-muted">
                    {t('staff_settings.holidays.intro')}
                </p>
            </div>

            <form
                className="flex flex-wrap items-end gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (value !== '') {
                        save([...dates, value]);
                    }
                }}
            >
                <div className="grid gap-1.5">
                    <Label htmlFor="holiday-date">
                        {t('staff_settings.holidays.add_label')}
                    </Label>
                    <Input
                        id="holiday-date"
                        type="date"
                        className="w-48"
                        value={value}
                        aria-invalid={error ? true : undefined}
                        onChange={(e) => setValue(e.target.value)}
                    />
                </div>
                <Button type="submit" disabled={disabled || value === ''}>
                    {t('staff_settings.holidays.add')}
                </Button>
                {missing && (
                    <Button
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        onClick={() =>
                            router.post(
                                '/staff/settings/holidays/next-year',
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <CalendarPlus aria-hidden="true" />
                        {t('staff_settings.holidays.next_year', {
                            year: nextYear.year,
                        })}
                    </Button>
                )}
            </form>
            {error && (
                <p role="alert" className="text-sm text-chilli">
                    {error}
                </p>
            )}
            {missing && (
                <p className="-mt-3 text-sm text-ink-muted">
                    {t('staff_settings.holidays.next_year_hint')}
                </p>
            )}
            {!context.online && (
                <p className="text-sm text-ink-muted" role="status">
                    {t('staff_settings.offline_reason')}
                </p>
            )}

            {dates.length === 0 ? (
                <EmptyState
                    title={t('staff_settings.holidays.empty_title')}
                    description={t('staff_settings.holidays.empty_body')}
                />
            ) : (
                <div className="grid gap-2">
                    <p className="text-label text-ink-muted uppercase">
                        {choice(
                            t('staff_settings.holidays.count', {
                                count: dates.length,
                            }),
                            dates.length,
                        )}
                    </p>
                    <ul className="grid divide-y divide-line rounded-md border border-line">
                        {dates.map((date) => (
                            <li
                                key={date}
                                className="flex min-h-12 items-center justify-between gap-3 px-3"
                            >
                                <span
                                    className={
                                        date < today ? 'text-ink-muted' : ''
                                    }
                                >
                                    {label(date)}
                                    {date < today && (
                                        <span className="ml-2 text-sm">
                                            ({t('staff_settings.holidays.past')}
                                            )
                                        </span>
                                    )}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={disabled}
                                    aria-label={t(
                                        'staff_settings.holidays.remove',
                                        { date: label(date) },
                                    )}
                                    onClick={() =>
                                        save(dates.filter((d) => d !== date))
                                    }
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}

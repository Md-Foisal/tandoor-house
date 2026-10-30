import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/lib/i18n';
import { SectionCard } from './section-card';
import type { DayHours, OpenNow, SettingsContext } from './types';

const MAX_PERIODS = 3;

export function HoursSection({
    hours,
    openNow,
    context,
}: {
    hours: DayHours[];
    openNow: OpenNow;
    context: SettingsContext;
}) {
    const t = useT();
    const form = useForm<{ days: DayHours[] }>({ days: hours });
    const errors = form.errors as Record<string, string | undefined>;

    const change = (index: number, next: Partial<DayHours>) =>
        form.setData(
            'days',
            form.data.days.map((d, i) => (i === index ? { ...d, ...next } : d)),
        );

    const toggle = (index: number, open: boolean) => {
        const day = form.data.days[index];
        change(index, {
            closed: !open,
            periods:
                open && day.periods.length === 0
                    ? [{ open: '12:00', close: '22:00' }]
                    : day.periods,
        });
    };

    const setPeriod = (
        index: number,
        p: number,
        key: 'open' | 'close',
        value: string,
    ) =>
        change(index, {
            periods: form.data.days[index].periods.map((x, i) =>
                i === p ? { ...x, [key]: value } : x,
            ),
        });

    const status = openNow.isOpen
        ? t('staff_settings.hours.now_open', { time: openNow.until ?? '' })
        : openNow.opensAt
          ? t('staff_settings.hours.now_closed', { when: openNow.opensAt })
          : t('staff_settings.hours.now_closed_never');

    return (
        <SectionCard
            title={t('staff_settings.sections.hours.title')}
            description={t('staff_settings.hours.intro')}
            dirty={form.isDirty}
            processing={form.processing}
            online={context.online}
            onSubmit={() =>
                form.put('/staff/settings/hours', {
                    preserveScroll: true,
                    onSuccess: () => form.setDefaults(),
                })
            }
        >
            <p className="text-sm text-ink-muted" role="status">
                {status}
            </p>
            <ul className="grid divide-y divide-line">
                {form.data.days.map((day, index) => {
                    const name = t(`staff_settings.days.${day.day}`);

                    return (
                        <li
                            key={day.day}
                            className="grid gap-3 py-4 md:grid-cols-[11rem_1fr]"
                        >
                            <div className="flex min-h-11 items-center gap-3">
                                <Switch
                                    id={`open-${day.day}`}
                                    checked={!day.closed}
                                    onCheckedChange={(v) => toggle(index, v)}
                                    aria-label={t(
                                        'staff_settings.hours.open_toggle',
                                        { day: name },
                                    )}
                                />
                                <label
                                    htmlFor={`open-${day.day}`}
                                    className="font-semibold"
                                >
                                    {name}
                                </label>
                            </div>
                            <div className="grid gap-2">
                                {day.closed ? (
                                    <p className="flex min-h-11 items-center text-ink-muted">
                                        {t('staff_settings.hours.closed')}
                                    </p>
                                ) : (
                                    <>
                                        {day.periods.map((period, p) => {
                                            const key = `days.${index}.periods.${p}`;
                                            const message =
                                                errors[`${key}.close`] ??
                                                errors[`${key}.open`];

                                            return (
                                                <div key={p}>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Input
                                                            type="time"
                                                            className="w-32"
                                                            aria-label={`${name}, ${t('staff_settings.hours.period', { number: p + 1 })}: ${t('staff_settings.hours.open')}`}
                                                            aria-invalid={
                                                                message
                                                                    ? true
                                                                    : undefined
                                                            }
                                                            value={period.open}
                                                            onChange={(e) =>
                                                                setPeriod(
                                                                    index,
                                                                    p,
                                                                    'open',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                        <span aria-hidden="true">
                                                            –
                                                        </span>
                                                        <Input
                                                            type="time"
                                                            className="w-32"
                                                            aria-label={`${name}, ${t('staff_settings.hours.period', { number: p + 1 })}: ${t('staff_settings.hours.close')}`}
                                                            aria-invalid={
                                                                message
                                                                    ? true
                                                                    : undefined
                                                            }
                                                            value={period.close}
                                                            onChange={(e) =>
                                                                setPeriod(
                                                                    index,
                                                                    p,
                                                                    'close',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={t(
                                                                'staff_settings.hours.remove_period',
                                                                {
                                                                    number:
                                                                        p + 1,
                                                                    day: name,
                                                                },
                                                            )}
                                                            onClick={() =>
                                                                change(index, {
                                                                    periods:
                                                                        day.periods.filter(
                                                                            (
                                                                                _,
                                                                                i,
                                                                            ) =>
                                                                                i !==
                                                                                p,
                                                                        ),
                                                                })
                                                            }
                                                        >
                                                            <X aria-hidden="true" />
                                                        </Button>
                                                    </div>
                                                    {message && (
                                                        <p
                                                            role="alert"
                                                            className="mt-1 text-sm text-chilli"
                                                        >
                                                            {message}
                                                        </p>
                                                    )}
                                                </div>
                                            );
                                        })}
                                        {errors[`days.${index}.periods`] && (
                                            <p
                                                role="alert"
                                                className="text-sm text-chilli"
                                            >
                                                {
                                                    errors[
                                                        `days.${index}.periods`
                                                    ]
                                                }
                                            </p>
                                        )}
                                        <div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    day.periods.length >=
                                                    MAX_PERIODS
                                                }
                                                onClick={() =>
                                                    change(index, {
                                                        periods: [
                                                            ...day.periods,
                                                            {
                                                                open: '17:30',
                                                                close: '22:30',
                                                            },
                                                        ],
                                                    })
                                                }
                                            >
                                                <Plus aria-hidden="true" />
                                                {t(
                                                    'staff_settings.hours.add_period',
                                                )}
                                            </Button>
                                            {day.periods.length >=
                                                MAX_PERIODS && (
                                                <span className="ml-3 text-sm text-ink-muted">
                                                    {t(
                                                        'staff_settings.hours.max_periods',
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>
        </SectionCard>
    );
}

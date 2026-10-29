import { router } from '@inertiajs/react';
import { LogIn, LogOut } from 'lucide-react';
import { useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { clockTime, type ShiftInfo } from './types';

export function ShiftCard({
    info,
    error,
}: {
    info: ShiftInfo;
    error?: string;
}) {
    const t = useT();
    const online = useOnline();
    const [busy, setBusy] = useState(false);
    const clockedIn = info.clocked_in_at !== null;

    const send = () => {
        setBusy(true);
        router.post(
            clockedIn ? '/staff/clock-out' : '/staff/clock-in',
            {},
            {
                preserveScroll: true,
                only: ['shift', 'blocks', 'errors'],
                onFinish: () => setBusy(false),
            },
        );
    };

    const status = clockedIn
        ? t('staff_today.shift.on_the_clock', {
              time: clockTime(info.clocked_in_at as string),
          })
        : info.last_clocked_out_at
          ? t('staff_today.shift.finished', {
                time: clockTime(info.last_clocked_out_at),
            })
          : t('staff_today.shift.not_started');

    return (
        <section
            aria-labelledby="shift-title"
            className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div className="min-w-0">
                <h2 id="shift-title" className="text-title font-semibold">
                    {t('staff_today.shift.title')}
                </h2>
                {info.shift ? (
                    <p className="mt-1 text-ink">
                        {t('staff_today.shift.starts', {
                            start: clockTime(info.shift.starts_at),
                            end: clockTime(info.shift.ends_at),
                            role: t(`roles.${info.shift.role}`),
                        })}
                    </p>
                ) : (
                    <>
                        <p className="mt-1">{t('staff_today.shift.none')}</p>
                        <p className="text-sm text-ink-muted">
                            {t('staff_today.shift.none_hint')}
                        </p>
                    </>
                )}
                {info.shift?.notes && (
                    <p className="text-sm text-ink-muted">
                        {t('staff_today.shift.notes', {
                            notes: info.shift.notes,
                        })}
                    </p>
                )}
                <p
                    className="mt-1 text-sm font-semibold text-ink-muted"
                    aria-live="polite"
                >
                    {status}
                </p>
                {error && (
                    <p role="alert" className="mt-1 text-sm text-chilli">
                        {error}
                    </p>
                )}
            </div>
            <div className="flex flex-col items-stretch gap-1 sm:items-end">
                <Button
                    size="lg"
                    variant={clockedIn ? 'outline' : 'default'}
                    onClick={send}
                    disabled={busy || !online}
                    className="min-h-12 sm:min-w-44"
                >
                    {clockedIn ? <LogOut aria-hidden /> : <LogIn aria-hidden />}
                    {clockedIn
                        ? t('staff_today.clock.out')
                        : t('staff_today.clock.in')}
                </Button>
                {!online && (
                    <p className="text-sm text-ink-muted">
                        {t('staff_today.clock.offline_reason')}
                    </p>
                )}
            </div>
        </section>
    );
}

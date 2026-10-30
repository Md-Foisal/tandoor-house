import { Clock } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useT } from '@/lib/i18n';

function secondsLeft(expiresAt: string): number {
    return Math.max(
        0,
        Math.round((new Date(expiresAt).getTime() - Date.now()) / 1000),
    );
}

/**
 * How long the table is held for an unpaid deposit. Announces only the
 * minute, so screen readers are not read every second.
 */
export function HoldTimer({ expiresAt }: { expiresAt: string }) {
    const t = useT();
    const [left, setLeft] = useState(() => secondsLeft(expiresAt));

    useEffect(() => {
        const id = window.setInterval(
            () => setLeft(secondsLeft(expiresAt)),
            1000,
        );

        return () => window.clearInterval(id);
    }, [expiresAt]);

    const minutes = Math.floor(left / 60);
    const clock = `${minutes}:${String(left % 60).padStart(2, '0')}`;

    return (
        <p className="flex items-center gap-2 text-sm text-ink-muted">
            <Clock className="size-4 shrink-0" aria-hidden="true" />
            <span aria-hidden="true">
                {left > 0
                    ? t('booking.pay.hold', { time: clock })
                    : t('booking.pay.hold_ended')}
            </span>
            <span className="sr-only" role="status">
                {left > 0
                    ? t('booking.pay.hold_sr', { minutes: minutes + 1 })
                    : t('booking.pay.hold_ended')}
            </span>
        </p>
    );
}

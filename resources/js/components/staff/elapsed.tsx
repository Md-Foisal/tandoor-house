import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

export type WaitTone = 'fresh' | 'waiting' | 'late';

/**
 * Minutes since a moment, ticking every 15 seconds. Thresholds colour the
 * text: under `warnAfter` minutes fresh, then waiting, then late.
 */
export function useElapsedMinutes(since: string | null): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 15_000);

        return () => window.clearInterval(timer);
    }, []);

    if (since === null) {
        return 0;
    }

    return Math.max(0, Math.floor((now - new Date(since).getTime()) / 60_000));
}

export function waitTone(
    minutes: number,
    warnAfter: number,
    lateAfter: number,
): WaitTone {
    if (minutes >= lateAfter) {
        return 'late';
    }

    return minutes >= warnAfter ? 'waiting' : 'fresh';
}

export function Elapsed({
    since,
    warnAfter = 10,
    lateAfter = 20,
    className,
}: {
    since: string | null;
    warnAfter?: number;
    lateAfter?: number;
    className?: string;
}) {
    const minutes = useElapsedMinutes(since);
    const tone = waitTone(minutes, warnAfter, lateAfter);

    return (
        <span
            className={cn(
                'font-semibold tabular-nums',
                tone === 'fresh' && 'text-ink-muted',
                tone === 'waiting' && 'text-turmeric',
                tone === 'late' && 'text-chilli',
                className,
            )}
        >
            {minutes}′
        </span>
    );
}

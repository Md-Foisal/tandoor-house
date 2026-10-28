import { useEffect, useState } from 'react';

export function useNow(everyMs = 15_000): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), everyMs);

        return () => window.clearInterval(timer);
    }, [everyMs]);

    return now;
}

const clock = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Europe/London',
});

export function formatClock(iso: string): string {
    return clock.format(new Date(iso));
}

export function minutesSince(iso: string | null, now: number): number {
    return iso === null
        ? 0
        : Math.max(0, Math.floor((now - new Date(iso).getTime()) / 60_000));
}

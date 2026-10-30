import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useOnline } from '@/components/brand/states';
import type { Booking } from './types';

const POLL_MS = 30_000;
const HIGHLIGHT_MS = 5_000;

/**
 * Today's day view refreshes itself every 30 seconds while the tab is
 * visible and the device is online. Other days only change when someone
 * edits them, so they do not poll.
 */
export function useBoardPoll(active: boolean) {
    const online = useOnline();
    const [failed, setFailed] = useState(false);
    const [updatedAt, setUpdatedAt] = useState<Date>(() => new Date());
    const [refreshing, setRefreshing] = useState(false);

    useEffect(() => {
        if (!active || !online) {
            return;
        }

        const refresh = () => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            router.reload({
                only: ['bookings', 'services', 'now'],
                onStart: () => setRefreshing(true),
                onSuccess: () => {
                    setFailed(false);
                    setUpdatedAt(new Date());
                },
                onError: () => setFailed(true),
                onNetworkError: () => setFailed(true),
                onHttpException: () => {
                    setFailed(true);

                    return false;
                },
                onFinish: () => setRefreshing(false),
            });
        };

        const timer = window.setInterval(refresh, POLL_MS);
        document.addEventListener('visibilitychange', refresh);

        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', refresh);
        };
    }, [active, online]);

    return { failed, updatedAt, refreshing, retry: () => router.reload() };
}

/**
 * Minutes since midnight at the restaurant, ticking between polls from the
 * server's clock so a staff tablet with a wrong clock still shows the line
 * in the right place.
 */
export function useServerMinute(serverMinute: number, serverIso: string) {
    const arrived = useRef(Date.now());
    const [, tick] = useState(0);

    useEffect(() => {
        arrived.current = Date.now();
        tick((n) => n + 1);
    }, [serverIso]);

    useEffect(() => {
        const timer = window.setInterval(() => tick((n) => n + 1), 30_000);

        return () => window.clearInterval(timer);
    }, []);

    return serverMinute + (Date.now() - arrived.current) / 60_000;
}

/**
 * Ids of bookings that appeared since the last render, for a single quiet
 * highlight. The first load highlights nothing.
 */
export function useNewBookingIds(bookings: Booking[]): Set<number> {
    const known = useRef<Set<number> | null>(null);
    const [fresh, setFresh] = useState<Set<number>>(new Set());

    useEffect(() => {
        const ids = new Set(bookings.map((b) => b.id));

        if (known.current !== null) {
            const added = [...ids].filter((id) => !known.current?.has(id));

            if (added.length > 0) {
                setFresh(new Set(added));
                const timer = window.setTimeout(
                    () => setFresh(new Set()),
                    HIGHLIGHT_MS,
                );
                known.current = ids;

                return () => window.clearTimeout(timer);
            }
        }

        known.current = ids;
    }, [bookings]);

    return fresh;
}

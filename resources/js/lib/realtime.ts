import { router, usePage, usePoll } from '@inertiajs/react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { ChannelAuthorizationCallback } from 'pusher-js';
import { useCallback, useEffect, useRef, useSyncExternalStore } from 'react';

/** Mirrors App\Broadcasting\Channels. Every channel is private. */
export const channels = {
    staffOrders: 'staff.orders',
    staffKitchen: 'staff.kitchen',
    staffFloor: 'staff.floor',
    staffReservations: 'staff.reservations',
    rider: (riderId: number) => `staff.deliveries.${riderId}`,
    order: (number: string) => `orders.${number}`,
} as const;

/** The broadcastAs() names of the events in app/Events. */
export const events = {
    orderPlaced: 'order.placed',
    orderStatusChanged: 'order.status',
    kitchenLineUpdated: 'kitchen.line',
    tableRequestRaised: 'table.request',
    reservationUpdated: 'reservation.updated',
    riderAssigned: 'rider.assigned',
    tableStatusChanged: 'table.status',
} as const;

export type RealtimeEvent = (typeof events)[keyof typeof events];

export type RealtimePayload = Record<string, unknown>;

export type ConnectionState =
    | 'disabled'
    | 'idle'
    | 'connecting'
    | 'live'
    | 'unavailable'
    | 'offline';

type ChannelStatus = 'pending' | 'subscribed' | 'failed';

const env = import.meta.env;
const enabled =
    typeof window !== 'undefined' && Boolean(env.VITE_REVERB_APP_KEY);

let echo: Echo<'reverb'> | null = null;
let socketState = 'initialized';
let online = typeof navigator === 'undefined' ? true : navigator.onLine;
const channelStatus = new Map<string, ChannelStatus>();
const channelUsers = new Map<string, number>();
const subscribers = new Set<() => void>();
let version = 0;

function notify(): void {
    version += 1;
    subscribers.forEach((callback) => callback());
}

function subscribeStore(callback: () => void): () => void {
    subscribers.add(callback);

    return () => subscribers.delete(callback);
}

function connectionState(): ConnectionState {
    if (!enabled) {
        return 'disabled';
    }

    if (!online) {
        return 'offline';
    }

    // No page has subscribed yet, so no socket has been opened.
    if (echo === null) {
        return 'idle';
    }

    if (socketState === 'connected') {
        return 'live';
    }

    return socketState === 'initialized' || socketState === 'connecting'
        ? 'connecting'
        : 'unavailable';
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

// Authorise with the XSRF cookie Laravel already sets, since the app has no
// csrf-token meta tag for Echo's default authoriser to read.
function authorize(
    params: { socketId: string; channelName: string },
    callback: ChannelAuthorizationCallback,
): void {
    fetch('/broadcasting/auth', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: new URLSearchParams({
            socket_id: params.socketId,
            channel_name: params.channelName,
        }),
    })
        .then((response) =>
            response.ok
                ? response.json()
                : Promise.reject(new Error(`Channel auth ${response.status}`)),
        )
        .then((data) => callback(null, data))
        .catch((error: Error) => callback(error, null));
}

function getEcho(): Echo<'reverb'> | null {
    if (!enabled) {
        return null;
    }

    if (echo) {
        return echo;
    }

    const scheme = env.VITE_REVERB_SCHEME ?? 'https';
    const port = Number(
        env.VITE_REVERB_PORT ?? (scheme === 'https' ? 443 : 80),
    );

    echo = new Echo({
        broadcaster: 'reverb',
        Pusher,
        key: env.VITE_REVERB_APP_KEY,
        wsHost: env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        channelAuthorization: { customHandler: authorize },
        withoutInterceptors: true,
    });

    echo.connector.pusher.connection.bind(
        'state_change',
        ({ current }: { current: string }) => {
            socketState = current;
            notify();
        },
    );

    return echo;
}

function subscribe(
    name: string,
    eventNames: string[],
    handler: (event: string, payload: RealtimePayload) => void,
): () => void {
    const client = getEcho();

    if (!client) {
        return () => {};
    }

    const channel = client.private(name);
    const users = channelUsers.get(name) ?? 0;

    if (users === 0) {
        channelStatus.set(name, 'pending');
        channel
            .subscribed(() => {
                channelStatus.set(name, 'subscribed');
                notify();
            })
            .error(() => {
                channelStatus.set(name, 'failed');
                notify();
            });
    }

    channelUsers.set(name, users + 1);

    const bound = eventNames.map((event) => {
        const callback = (payload: RealtimePayload) => handler(event, payload);
        channel.listen(`.${event}`, callback);

        return [event, callback] as const;
    });

    return () => {
        bound.forEach(([event, callback]) =>
            channel.stopListening(`.${event}`, callback),
        );

        const remaining = (channelUsers.get(name) ?? 1) - 1;

        if (remaining > 0) {
            channelUsers.set(name, remaining);

            return;
        }

        channelUsers.delete(name);
        channelStatus.delete(name);
        client.leave(name);
        notify();
    };
}

if (typeof window !== 'undefined') {
    const setOnline = (value: boolean) => {
        online = value;
        notify();
    };

    window.addEventListener('online', () => setOnline(true));
    window.addEventListener('offline', () => setOnline(false));
}

/** 'live' only while the socket is connected and the browser is online. */
export function useConnectionState(): ConnectionState {
    return useSyncExternalStore(
        subscribeStore,
        connectionState,
        () => 'disabled',
    );
}

/**
 * Listens on a private channel for the given events. Pass `null` as the name
 * to stay unsubscribed. Guests are never subscribed, as every channel needs a
 * signed-in user. `live` is true while events are actually arriving.
 */
export function useChannel(
    name: string | null,
    eventNames: readonly RealtimeEvent[],
    onEvent: (event: RealtimeEvent, payload: RealtimePayload) => void,
): { live: boolean } {
    const signedIn = usePage().props.auth.user !== null;
    const handler = useRef(onEvent);
    const eventKey = eventNames.join('|');
    const active = name !== null && signedIn && enabled;

    useEffect(() => {
        handler.current = onEvent;
    });

    useEffect(() => {
        if (!active || name === null) {
            return;
        }

        return subscribe(name, eventKey.split('|'), (event, payload) =>
            handler.current(event as RealtimeEvent, payload),
        );
    }, [active, name, eventKey]);

    useSyncExternalStore(
        subscribeStore,
        () => version,
        () => 0,
    );

    return {
        live:
            active &&
            connectionState() === 'live' &&
            channelStatus.get(name) === 'subscribed',
    };
}

type UseLiveOptions = {
    channel: string | null;
    events: readonly RealtimeEvent[];
    /** How often to poll while the socket is not live. */
    fallbackMs?: number;
    /** Bursts of events (a whole order bumped at once) become one reload. */
    debounceMs?: number;
};

/**
 * Reloads the named Inertia props whenever one of the events arrives on the
 * channel, and polls every `fallbackMs` instead while the socket is down,
 * unconfigured, or the viewer is a guest. Polling pauses while offline and
 * the props reload as soon as the connection is back.
 */
export function useLive(
    reloadProps: readonly string[],
    {
        channel,
        events: eventNames,
        fallbackMs = 15000,
        debounceMs = 300,
    }: UseLiveOptions,
): { live: boolean; connection: ConnectionState; reload: () => void } {
    const propKey = reloadProps.join('|');
    const timer = useRef<number | undefined>(undefined);

    const reload = useCallback(() => {
        router.reload({ only: propKey.split('|') });
    }, [propKey]);

    const schedule = useCallback(() => {
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(reload, debounceMs);
    }, [reload, debounceMs]);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const { live } = useChannel(channel, eventNames, schedule);
    const connection = useConnectionState();
    const { start, stop } = usePoll(
        fallbackMs,
        { only: propKey.split('|') },
        { autoStart: false },
    );
    const shouldPoll = !live && connection !== 'offline';
    const poller = useRef({ start, stop });

    useEffect(() => {
        poller.current = { start, stop };
    });

    useEffect(() => {
        if (shouldPoll) {
            poller.current.start();
        } else {
            poller.current.stop();
        }
    }, [shouldPoll]);

    // Events sent while the socket was down are gone, so catch up once when
    // it comes back. The first subscription after page load needs no catch-up.
    const hasBeenLive = useRef(false);
    const wasLive = useRef(live);
    const wasOffline = useRef(connection === 'offline');

    useEffect(() => {
        const cameBack =
            (live && !wasLive.current && hasBeenLive.current) ||
            (wasOffline.current && connection !== 'offline');

        if (cameBack) {
            schedule();
        }

        hasBeenLive.current ||= live;
        wasLive.current = live;
        wasOffline.current = connection === 'offline';
    }, [live, connection, schedule]);

    return { live, connection, reload };
}

const LOCALE = 'en-GB';
const TIME_ZONE = 'Europe/London';

const clock = new Intl.DateTimeFormat(LOCALE, {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: TIME_ZONE,
});

const dayAndClock = new Intl.DateTimeFormat(LOCALE, {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: TIME_ZONE,
});

/** "18:45", always in the restaurant's time, wherever the screen is. */
export const formatTime = (iso: string): string => clock.format(new Date(iso));

/** "29 Oct, 18:45" */
export const formatStamp = (iso: string): string =>
    dayAndClock.format(new Date(iso));

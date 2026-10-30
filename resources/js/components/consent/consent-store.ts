/*
 * The visitor's cookie choice, kept in the strictly necessary first-party
 * cookie `cookie_consent` (value "v<version>:preferences" or "v<version>:essential").
 * Name, version and lifetime mirror config/cookies.php → consent.
 *
 * Until preferences are accepted, nothing non-essential is written: the
 * stores below are emptied on load, on every page visit and when the tab
 * is left, so a component that writes one directly cannot make it stick.
 */
export const CONSENT_COOKIE = 'cookie_consent';
export const CONSENT_VERSION = 1;
const CONSENT_DAYS = 365;

const PREFERENCE_COOKIES = ['appearance', 'sidebar_state'] as const;
const PREFERENCE_LOCAL_KEYS = [
    'appearance',
    'kitchen-theme',
    'kitchen-station',
    'th-orders-chime',
] as const;

type Choice = 'preferences' | 'essential';

type Snapshot = {
    /** True once a choice for the current version is on record. */
    decided: boolean;
    preferences: boolean;
    /** Whether the banner is showing: first visit, or reopened by the visitor. */
    open: boolean;
};

const listeners = new Set<() => void>();
const serverSnapshot: Snapshot = {
    decided: true,
    preferences: false,
    open: false,
};

let snapshot: Snapshot | null = null;
let reopened = false;

function readCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(
        new RegExp(`(?:^|;\\s*)${name}=([^;]*)`),
    );

    return match ? decodeURIComponent(match[1]) : null;
}

function readChoice(): Choice | null {
    const value = readCookie(CONSENT_COOKIE);

    if (value === `v${CONSENT_VERSION}:preferences`) {
        return 'preferences';
    }

    if (value === `v${CONSENT_VERSION}:essential`) {
        return 'essential';
    }

    return null;
}

function compute(): Snapshot {
    const choice = readChoice();

    return {
        decided: choice !== null,
        preferences: choice === 'preferences',
        open: choice === null || reopened,
    };
}

function refresh(): void {
    snapshot = compute();
    listeners.forEach((listener) => listener());
}

export function getSnapshot(): Snapshot {
    snapshot ??= compute();

    return snapshot;
}

export function getServerSnapshot(): Snapshot {
    return serverSnapshot;
}

export function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function hasPreferenceConsent(): boolean {
    return readChoice() === 'preferences';
}

export function expireCookie(name: string): void {
    document.cookie = `${name}=; path=/; max-age=0; SameSite=Lax`;
}

/** Writes a preferences cookie, or does nothing without consent. */
export function writePreferenceCookie(
    name: string,
    value: string,
    days: number,
): void {
    if (typeof document === 'undefined' || !hasPreferenceConsent()) {
        return;
    }

    document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${days * 86400}; SameSite=Lax`;
}

/** Removes every preference cookie and storage key. Safe to call any time. */
export function clearPreferences(): void {
    if (typeof document === 'undefined') {
        return;
    }

    PREFERENCE_COOKIES.forEach(expireCookie);

    try {
        PREFERENCE_LOCAL_KEYS.forEach((key) =>
            window.localStorage.removeItem(key),
        );
    } catch {
        // Storage blocked by the browser: nothing was written either.
    }
}

/** Run on load, each visit and page hide. Does nothing once consent is given. */
export function enforceConsent(): void {
    if (!hasPreferenceConsent()) {
        clearPreferences();
    }
}

export function saveChoice(preferences: boolean): void {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    const value = `v${CONSENT_VERSION}:${preferences ? 'preferences' : 'essential'}`;

    document.cookie = `${CONSENT_COOKIE}=${value}; path=/; max-age=${CONSENT_DAYS * 86400}; SameSite=Lax${secure}`;

    if (!preferences) {
        clearPreferences();
    }

    reopened = false;
    refresh();
}

export function reopenBanner(): void {
    reopened = true;
    refresh();
}

export function closeBanner(): void {
    reopened = false;
    refresh();
}

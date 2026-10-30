import { useSyncExternalStore } from 'react';
import {
    closeBanner,
    getServerSnapshot,
    getSnapshot,
    reopenBanner,
    saveChoice,
    subscribe,
} from '@/components/consent/consent-store';

/** The visitor's cookie choice and the ways to change it. */
export function useConsent() {
    const state = useSyncExternalStore(
        subscribe,
        getSnapshot,
        getServerSnapshot,
    );

    return {
        ...state,
        accept: () => saveChoice(true),
        reject: () => saveChoice(false),
        save: saveChoice,
        reopen: reopenBanner,
        close: closeBanner,
    } as const;
}

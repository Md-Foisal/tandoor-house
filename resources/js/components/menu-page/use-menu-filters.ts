import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { MenuFilters } from '@/components/menu-page/filters';
import {
    emptyFilters,
    MENU_QUERY_STORAGE_KEY,
    parseFilters,
    serialiseFilters,
} from '@/components/menu-page/filters';

function initialSearch(url: string): string {
    if (typeof window !== 'undefined') {
        return window.location.search;
    }

    const index = url.indexOf('?');

    return index === -1 ? '' : url.slice(index);
}

/**
 * Filters live in the query string so they survive a refresh and can be
 * shared. The URL is rewritten in place (same history entry, same Inertia
 * state) so changing a filter never reloads the page.
 */
export function useMenuFilters() {
    const { url } = usePage();
    const [filters, setFilters] = useState<MenuFilters>(() =>
        parseFilters(initialSearch(url)),
    );
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const search = serialiseFilters(filters);
        const next = `${window.location.pathname}${search}${window.location.hash}`;

        window.history.replaceState(window.history.state, '', next);

        try {
            window.sessionStorage.setItem(MENU_QUERY_STORAGE_KEY, search);
        } catch {
            // Storage can be blocked; the back link then simply has no filters.
        }
    }, [filters]);

    useEffect(() => {
        try {
            window.sessionStorage.setItem(
                MENU_QUERY_STORAGE_KEY,
                serialiseFilters(filters),
            );
        } catch {
            // See above.
        }
        // Only the initial state needs storing here; later changes are stored above.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const update = useCallback(
        (patch: Partial<MenuFilters>) =>
            setFilters((current) => ({ ...current, ...patch })),
        [],
    );

    const clear = useCallback(() => setFilters(emptyFilters), []);

    return { filters, update, clear };
}

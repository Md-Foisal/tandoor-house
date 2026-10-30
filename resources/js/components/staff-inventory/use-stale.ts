import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** True after a refresh fails, until the next one succeeds. */
export function useStale(): boolean {
    const [stale, setStale] = useState(false);

    useEffect(() => {
        const off = [
            router.on('httpException', () => setStale(true)),
            router.on('networkError', () => setStale(true)),
            router.on('success', () => setStale(false)),
        ];

        return () => off.forEach((stop) => stop());
    }, []);

    return stale;
}

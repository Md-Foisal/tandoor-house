import { useState } from 'react';

/**
 * Ids that appeared since the previous render with different data. The first
 * render marks nothing as new, so a page load does not pulse everything.
 */
export function useNewIds(ids: number[]): Set<number> {
    const key = ids.join(',');
    const [state, setState] = useState(() => ({
        key,
        seen: new Set(ids),
        fresh: new Set<number>(),
    }));

    if (state.key !== key) {
        setState({
            key,
            seen: new Set([...state.seen, ...ids]),
            fresh: new Set(ids.filter((id) => !state.seen.has(id))),
        });
    }

    return state.fresh;
}

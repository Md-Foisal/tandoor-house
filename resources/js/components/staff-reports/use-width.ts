import { useEffect, useRef, useState } from 'react';

/** Width of an element in pixels, kept current as it resizes. */
export function useWidth<T extends HTMLElement>(fallback = 640) {
    const ref = useRef<T>(null);
    const [width, setWidth] = useState(fallback);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        setWidth(element.clientWidth || fallback);

        const observer = new ResizeObserver(([entry]) => {
            setWidth(Math.round(entry.contentRect.width) || fallback);
        });
        observer.observe(element);

        return () => observer.disconnect();
    }, [fallback]);

    return [ref, width] as const;
}

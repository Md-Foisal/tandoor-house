import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export const allergenCodes = [
    'celery',
    'gluten',
    'crustaceans',
    'eggs',
    'fish',
    'lupin',
    'milk',
    'molluscs',
    'mustard',
    'tree_nuts',
    'peanuts',
    'sesame',
    'soya',
    'sulphites',
] as const;

export type AllergenCode = (typeof allergenCodes)[number];

/*
 * The house allergen set: 24px grid, 1.75px round strokes, drawn to sit
 * next to the ember line. Icons always appear with their written name.
 */
const glyphs: Record<AllergenCode, ReactNode> = {
    celery: (
        <>
            <path d="M9 21c-.6-4.5-.6-9 .4-13M12.5 21c-.2-4.6 0-9 .8-13M16 21c.2-4 .1-8-.6-12" />
            <path d="M9.4 8C7.6 6.6 6.8 4.8 7.2 3c1.8.3 3 1.6 3.4 3.4M13.3 8c-.3-2 .4-3.8 2-5 1.1 1.5 1.1 3.4-.2 5M15.4 9c1.4-1.3 3.2-1.8 5-1.3-.7 1.7-2.3 2.6-4.3 2.4" />
        </>
    ),
    gluten: (
        <>
            <path d="M12 22V7" />
            <path d="M12 10c-2.6-.2-4-1.9-4-4.4 2.4.1 4 1.8 4 4.4ZM12 10c2.6-.2 4-1.9 4-4.4-2.4.1-4 1.8-4 4.4ZM12 15c-2.6-.2-4-1.9-4-4.4 2.4.1 4 1.8 4 4.4ZM12 15c2.6-.2 4-1.9 4-4.4-2.4.1-4 1.8-4 4.4ZM12 6.5c-1-1-1.3-2.4-.1-4.5 1.3 2 1.1 3.5.1 4.5Z" />
        </>
    ),
    crustaceans: (
        <>
            <path d="M6 9.5C6 6 9 3.5 12.6 3.8 17.4 4.2 20.6 8.6 20 13.4c-.6 4.6-4.7 7.4-9 6.6" />
            <path d="M11 20l-2.6 1.6.4-3.1M10 7.5c2.6.3 4.4 2.3 4.4 4.9M8 13c1.7-.6 3.7-.3 5 1M6 9.5 3 6M6 9.5 2.8 10.4" />
        </>
    ),
    eggs: (
        <path d="M12 21c-3.8 0-6.5-2.8-6.5-6.8C5.5 9 8.4 3 12 3s6.5 6 6.5 11.2c0 4-2.7 6.8-6.5 6.8Z" />
    ),
    fish: (
        <>
            <path d="M3 12c2.6-4 6-6 9.6-6 3.6 0 6.2 2.3 7.6 6-1.4 3.7-4 6-7.6 6C9 18 5.6 16 3 12Z" />
            <path d="M20.2 12 22 8.5M20.2 12l1.8 3.5M7.5 10.5c.8 1 .8 2 0 3" />
            <circle
                cx="15.5"
                cy="11"
                r=".9"
                fill="currentColor"
                stroke="none"
            />
        </>
    ),
    lupin: (
        <>
            <path d="M12 22V9" />
            <path d="M12 4.5c-1 0-1.6-.7-1.6-1.5S11 1.5 12 1.5s1.6.7 1.6 1.5-.6 1.5-1.6 1.5ZM9.4 8.5c-1.4 0-2.4-.9-2.4-2s1-2 2.4-2c1.1 0 1.9.7 2.3 1.6M14.6 8.5c1.4 0 2.4-.9 2.4-2s-1-2-2.4-2c-1.1 0-1.9.7-2.3 1.6M9 13c-1.6 0-2.8-1-2.8-2.3S7.4 8.5 9 8.5c1.3 0 2.3.8 2.8 1.8M15 13c1.6 0 2.8-1 2.8-2.3S16.6 8.5 15 8.5c-1.3 0-2.3.8-2.8 1.8" />
        </>
    ),
    milk: (
        <>
            <path d="M9.5 2.5h5M10 2.5v3.2L7.5 9.5V20a1.5 1.5 0 0 0 1.5 1.5h6a1.5 1.5 0 0 0 1.5-1.5V9.5L14 5.7V2.5" />
            <path d="M7.5 13.5c1.5-.8 3-.8 4.5 0s3 .8 4.5 0" />
        </>
    ),
    molluscs: (
        <>
            <path d="M12 21C6.5 21 3 16.6 3 11.5 3 7 6.6 3 12 3s9 4 9 8.5c0 5.1-3.5 9.5-9 9.5Z" />
            <path d="M12 21V8M12 21 7.5 9.5M12 21l4.5-11.5M12 21 5 13.5M12 21l7-7.5" />
        </>
    ),
    mustard: (
        <>
            <path d="M8 9h8l-.8 11.2a1.5 1.5 0 0 1-1.5 1.3h-3.4a1.5 1.5 0 0 1-1.5-1.3L8 9Z" />
            <path d="M7 9h10M12 9V2.5M10 14.5h4" />
            <circle cx="18.6" cy="4.4" r="1.1" />
            <circle cx="20.4" cy="7.4" r="1.1" />
        </>
    ),
    tree_nuts: (
        <>
            <path d="M12 21.5c-3.9 0-6.5-3.5-6.5-8.3C5.5 7.4 8.4 3 12 3s6.5 4.4 6.5 10.2c0 4.8-2.6 8.3-6.5 8.3Z" />
            <path d="M12 3v18.5M9 8.5c-.8 2.6-.8 5.4 0 8M15 8.5c.8 2.6.8 5.4 0 8" />
        </>
    ),
    peanuts: (
        <>
            <path d="M12 2.8c2.8 0 4.8 2 4.8 4.6 0 1.7-1 2.7-1 4.6s1 2.9 1 4.6c0 2.6-2 4.6-4.8 4.6s-4.8-2-4.8-4.6c0-1.7 1-2.7 1-4.6s-1-2.9-1-4.6c0-2.6 2-4.6 4.8-4.6Z" />
            <path
                d="M10 6.5h.01M14 6.5h.01M12 8.5h.01M10 16h.01M14 16h.01M12 18h.01"
                strokeWidth="2.2"
            />
        </>
    ),
    sesame: (
        <>
            <path d="M8 11.5c-1.5 0-2.5-1.6-2.5-3.6S6.8 3.5 8 3.5s2.5 2.4 2.5 4.4-1 3.6-2.5 3.6Z" />
            <path d="M16.5 11c-1.3.6-3-.3-3.8-2.1-.8-1.8-.5-4.7.6-5.2 1.1-.5 3.1 1.5 3.9 3.3.8 1.8.6 3.4-.7 4Z" />
            <path d="M12 20.5c-1.5 0-2.5-1.6-2.5-3.6s1.3-4.4 2.5-4.4 2.5 2.4 2.5 4.4-1 3.6-2.5 3.6Z" />
        </>
    ),
    soya: (
        <>
            <path d="M4.5 17.5C4 12 8 5.5 14.8 4.2c2.6-.5 4.7.6 4.7 2.8 0 5.5-6.4 12.8-12 12.8-1.8 0-2.9-.9-3-2.3Z" />
            <circle cx="9" cy="14.5" r="1.8" />
            <circle cx="12.6" cy="10.8" r="1.8" />
            <circle cx="16" cy="7.4" r="1.6" />
        </>
    ),
    sulphites: (
        <>
            <path d="M7 3h10l-.6 5.8A4.4 4.4 0 0 1 12 12.8a4.4 4.4 0 0 1-4.4-4L7 3ZM12 12.8V20M8.5 21h7" />
            <path d="M7.6 7h8.8" />
        </>
    ),
};

export function AllergenIcon({
    code,
    className,
}: {
    code: AllergenCode;
    className?: string;
}) {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.75"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={cn('size-5 shrink-0', className)}
        >
            {glyphs[code]}
        </svg>
    );
}

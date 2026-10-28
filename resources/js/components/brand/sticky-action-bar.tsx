import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Primary action pinned within thumb reach: above the tab bar on phones,
 * inline from 768px. Pages using it add `pb-40 md:pb-0` to their content.
 */
export function StickyActionBar({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t border-line bg-paper/95 px-4 py-3 backdrop-blur-sm md:static md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none',
                className,
            )}
        >
            {children}
        </div>
    );
}

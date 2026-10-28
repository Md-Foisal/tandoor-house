import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    ratio?: 'portrait' | 'square' | 'landscape';
    className?: string;
};

const ratios = {
    portrait: 'aspect-[4/5]',
    square: 'aspect-square',
    landscape: 'aspect-[3/2]',
} as const;

/**
 * The tandoor arch: the brand's signature frame for photography.
 */
export function ArchFrame({ children, ratio = 'portrait', className }: Props) {
    return (
        <div
            className={cn(
                'relative isolate overflow-hidden rounded-arch bg-clay',
                ratios[ratio],
                className,
            )}
        >
            {children}
        </div>
    );
}
